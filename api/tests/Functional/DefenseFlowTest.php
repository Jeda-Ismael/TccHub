<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\UserFactory;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Fase 5: da "Aguardando defesa" até Aprovado / Reprovado.
 */
final class DefenseFlowTest extends ApiTestCase
{
    private User $student;
    private User $advisor;
    private User $evaluator1;
    private User $evaluator2;
    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        SubmissionPeriodFactory::new()->submission()->create();
        SubmissionPeriodFactory::new()->evaluation()->create();
        $this->student = UserFactory::new()->student()->create(['name' => 'Ana']);
        $this->advisor = UserFactory::new()->advisor()->create(['name' => 'Olga']);
        $this->evaluator1 = UserFactory::new()->evaluator()->create(['name' => 'Arthur']);
        $this->evaluator2 = UserFactory::new()->evaluator()->create(['name' => 'Alice']);
        $this->coordination = UserFactory::new()->coordination()->create(['name' => 'Carla']);
    }

    private function as(User $user): string
    {
        return $this->loginAs($user);
    }

    private function id(User $user): string
    {
        return $user->getId()->toRfc4122();
    }

    /** Leva um TCC até "Aguardando defesa" pelo fluxo normal. */
    private function awaitingDefense(): string
    {
        $token = $this->as($this->student);
        $this->request('POST', '/api/tcc', ['title' => 'Detecção de fraudes', 'abstract' => 'Resumo', 'advisorId' => $this->id($this->advisor)], $token);
        $id = $this->json()['id'];
        $path = (string) tempnam(sys_get_temp_dir(), 'tcc');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $this->client->request('POST', "/api/tcc/$id/document", [], ['file' => new UploadedFile($path, 'tcc.pdf', 'application/pdf', null, true)], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->request('POST', "/api/tcc/$id/submit", token: $token);
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $this->as($this->coordination));
        $advisorToken = $this->as($this->advisor);
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $advisorToken);
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator2));
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $advisorToken);
        self::assertSame('AWAITING_DEFENSE', $this->json()['status']);

        return $id;
    }

    /** Defesa agendada para uma hora atrás: a banca já pode lançar notas. */
    private function scheduledInThePast(string $id): void
    {
        $at = (new \DateTimeImmutable('-1 hour'))->format(\DATE_ATOM);
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => $at, 'location' => 'Sala 101'], $this->as($this->advisor));
        self::assertResponseIsSuccessful();
    }

    /**
     * Notas [projeto, apresentação] de cada avaliador.
     *
     * @param array{0: float, 1: float} $grades1
     * @param array{0: float, 1: float} $grades2
     */
    private function bothGrade(string $id, array $grades1, array $grades2): void
    {
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => $grades1[0], 'presentationGrade' => $grades1[1]], $this->as($this->evaluator1));
        self::assertResponseIsSuccessful();
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => $grades2[0], 'presentationGrade' => $grades2[1], 'comment' => 'Boa apresentação'], $this->as($this->evaluator2));
        self::assertResponseIsSuccessful();
    }

    public function testApprovedWhenTheAverageReachesTheMinimum(): void
    {
        $id = $this->awaitingDefense();

        // Datas no formato do campo datetime-local valem no fuso da instituição
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2026-11-20T14:00', 'meetingUrl' => 'https://meet.example.com/abc'], $this->as($this->advisor));
        self::assertResponseIsSuccessful();
        self::assertSame('2026-11-20T14:00:00-03:00', $this->json()['defense']['scheduledAt']);
        self::assertSame('https://meet.example.com/abc', $this->json()['defense']['meetingUrl']);

        $this->scheduledInThePast($id);
        $this->bothGrade($id, [8.0, 9.0], [7.0, 10.0]);

        // Projeto (8+7)/2 = 7,50; apresentação (9+10)/2 = 9,50; final = 8,50
        $tcc = $this->json();
        self::assertSame('APPROVED', $tcc['status']);
        self::assertEquals(7.5, $tcc['defense']['projectAverage']);
        self::assertEquals(9.5, $tcc['defense']['presentationAverage']);
        self::assertEquals(8.5, $tcc['defense']['finalGrade']);
        self::assertEquals(8.5, $tcc['finalGrade']);
        self::assertSame('PASSED', $tcc['defense']['outcome']);
        self::assertSame('Defesa aprovada com média 8,50.', array_reverse($tcc['statusHistory'])[0]['comment']);
        // Todos os horários saem no fuso da instituição, para a tela mostrar como estão
        self::assertStringEndsWith('-03:00', $tcc['statusHistory'][0]['createdAt']);
        self::assertStringEndsWith('-03:00', $tcc['document']['uploadedAt']);

        // O aluno vê o resultado e as notas de cada avaliador
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->student));
        self::assertCount(2, $this->json()['defense']['grades']);
    }

    public function testDefenseMinutesPdfAfterTheDefenseCloses(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 8.0, 'presentationGrade' => 9.0], $this->as($this->evaluator1));
        // Defesa ainda aberta: sem ata
        self::assertNull($this->json()['defense']['minutesUrl']);

        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 7.0, 'presentationGrade' => 10.0, 'comment' => 'Boa apresentação'], $this->as($this->evaluator2));
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->student));
        $link = $this->json()['defense']['minutesUrl'];
        self::assertStringContainsString("/api/tcc/$id/defenses/1/minutes", $link);
        self::assertStringContainsString('_hash=', $link);

        // Abre num <a href>, sem token
        $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('ata-defesa-1.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());

        // Sem assinatura, ou com outra tentativa, não abre
        $this->client->request('GET', "/api/tcc/$id/defenses/1/minutes");
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', str_replace('/defenses/1/', '/defenses/2/', $link));
        self::assertResponseStatusCodeSame(403);
    }

    public function testDefenseMinutesContent(): void
    {
        $id = $this->awaitingDefense();
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2026-11-20T14:00', 'location' => 'Sala 101'], $this->as($this->advisor));
        $this->scheduledInThePast($id);
        $this->bothGrade($id, [8.0, 9.0], [7.0, 10.0]);

        $tcc = self::getContainer()->get(\App\Repository\TccRepository::class)->find(\Symfony\Component\Uid\Uuid::fromString($id));
        self::assertNotNull($tcc);
        $defense = $tcc->getCurrentDefense();
        self::assertNotNull($defense);
        $html = self::getContainer()->get('twig')->render('pdf/defense_minutes.html.twig', [
            'defense' => $defense,
            'tcc' => $tcc,
            'grades' => $defense->getGrades()->getValues(),
            // O serviço já passa as datas no fuso da instituição; o template não pode convertê-las de volta
            'scheduledAt' => (new \DateTimeImmutable('2026-11-20T17:00:00Z'))->setTimezone(new \DateTimeZone('America/Sao_Paulo')),
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        foreach (['Detecção de fraudes', 'Ana', 'Olga', 'Arthur', 'Alice', '8,50', '7,50', '9,50', 'aprovado(a) na defesa', 'Boa apresentação', 'Sala 101', '20 de novembro de 2026, às 14:00'] as $text) {
            self::assertStringContainsString($text, $html);
        }
    }

    public function testBelowTheMinimumGoesToCoordinationThatAllowsANewDefense(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $this->bothGrade($id, [5.0, 6.0], [5.5, 5.0]);

        // Final (5,25 + 5,50)/2 = 5,38 (meio centésimo arredonda para cima) < 6,00
        self::assertSame('AWAITING_DECISION', $this->json()['status']);
        self::assertEquals(5.38, $this->json()['defense']['finalGrade']);
        self::assertSame('FAILED', $this->json()['defense']['outcome']);

        $token = $this->as($this->coordination);
        $this->request('GET', "/api/tcc/$id", token: $token);
        self::assertTrue($this->json()['permissions']['decide']);

        $this->request('POST', "/api/tcc/$id/decision", ['action' => 'new_defense', 'comment' => 'Nova chance após ajustes'], $token);
        self::assertSame('AWAITING_DEFENSE', $this->json()['status']);
        self::assertSame(2, $this->json()['defense']['attempt']);
        self::assertNull($this->json()['defense']['scheduledAt']);
        self::assertSame([], $this->json()['defense']['grades']);
        self::assertSame([['attempt' => 1, 'outcome' => 'FAILED']], array_map(static fn ($d) => ['attempt' => $d['attempt'], 'outcome' => $d['outcome']], $this->json()['previousDefenses']));

        // Segunda tentativa aprova
        $this->scheduledInThePast($id);
        $this->bothGrade($id, [8.0, 8.0], [9.0, 9.0]);
        self::assertSame('APPROVED', $this->json()['status']);
        self::assertEquals(8.5, $this->json()['finalGrade']);
    }

    public function testCoordinationCanRejectAfterAFailedDefense(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $this->bothGrade($id, [2.0, 3.0], [3.0, 2.0]);
        $token = $this->as($this->coordination);

        $this->request('POST', "/api/tcc/$id/decision", ['action' => 'reject', 'comment' => ''], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Explique a decisão no comentário.'], $this->json()['message']);

        $this->request('POST', "/api/tcc/$id/decision", ['action' => 'reject', 'comment' => 'Não atingiu os requisitos mínimos'], $token);
        self::assertSame('REJECTED', $this->json()['status']);
    }

    public function testPassingGradeComesFromTheSettings(): void
    {
        $settings = self::getContainer()->get(SettingsRepository::class)->get();
        $settings->update(2, 3, '5.00');
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);

        $this->bothGrade($id, [5.0, 5.0], [5.0, 5.0]);

        self::assertSame('APPROVED', $this->json()['status'], 'Média 5,00 com mínima 5,00 aprova');
    }

    public function testGradesOnlyAfterTheDefenseStarts(): void
    {
        $id = $this->awaitingDefense();
        $token = $this->as($this->evaluator1);

        // Sem agendamento: nem aparece a opção
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 8, 'presentationGrade' => 8], $token);
        self::assertResponseStatusCodeSame(403);

        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2099-01-10T09:30', 'location' => 'Auditório'], $this->as($this->coordination));
        $this->request('GET', "/api/tcc/$id", token: $token);
        self::assertFalse($this->json()['permissions']['gradeDefense']);
        self::assertFalse($this->json()['defense']['hasStarted']);

        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 8, 'presentationGrade' => 8], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('As notas podem ser lançadas a partir do horário da defesa (10/01/2099 às 09:30).', $this->json()['message']);
    }

    public function testGradesAreBlindUntilTheDefenseCloses(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 9, 'presentationGrade' => 9], $this->as($this->evaluator1));

        // O outro avaliador e o aluno ainda não veem a nota
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->evaluator2));
        self::assertSame([], $this->json()['defense']['grades']);
        self::assertSame(1, $this->json()['defense']['gradedCount']);
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->student));
        self::assertSame([], $this->json()['defense']['grades']);

        // Quem lançou vê a própria; a coordenação acompanha
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->evaluator1));
        self::assertTrue($this->json()['defense']['grades'][0]['isMe']);
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->coordination));
        self::assertCount(1, $this->json()['defense']['grades']);
    }

    /** Quem está na banca não vê as notas dos colegas, mesmo tendo também o papel de coordenação. */
    public function testBoardMemberWhoIsAlsoCoordinationStaysBlind(): void
    {
        $this->evaluator2 = UserFactory::new()->withRoles([Role::Evaluator, Role::Coordination])->create(['name' => 'Alice']);
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 9, 'presentationGrade' => 9], $this->as($this->evaluator1));

        $this->request('GET', "/api/tcc/$id", token: $this->as($this->evaluator2));
        self::assertSame([], $this->json()['defense']['grades']);

        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 7, 'presentationGrade' => 7], $this->as($this->evaluator2));
        self::assertCount(2, $this->json()['defense']['grades']);
    }

    /**
     * Só a média final é arredondada. Arredondando as parciais antes, projeto
     * 5,995 → 6,00 e a final viraria 6,00 (aprovado); o valor exato é 5,9925 → 5,99.
     */
    public function testOnlyTheFinalGradeIsRounded(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $this->bothGrade($id, [5.99, 5.99], [6.00, 5.99]);

        $tcc = $this->json();
        self::assertSame('AWAITING_DECISION', $tcc['status']);
        self::assertEquals(6.00, $tcc['defense']['projectAverage']);
        self::assertEquals(5.99, $tcc['defense']['presentationAverage']);
        self::assertEquals(5.99, $tcc['defense']['finalGrade']);
    }

    public function testGradeCanBeCorrectedUntilTheDefenseCloses(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $token = $this->as($this->evaluator1);

        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 4, 'presentationGrade' => 4], $token);
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 8, 'presentationGrade' => 8.5], $token);

        self::assertEquals(8.5, $this->json()['defense']['grades'][0]['presentationGrade']);
        self::assertSame(1, $this->json()['defense']['gradedCount']);
    }

    public function testCannotRescheduleAfterGradesStarted(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 8, 'presentationGrade' => 8], $this->as($this->evaluator1));

        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2099-01-01T10:00', 'location' => 'Sala 2'], $this->as($this->advisor));

        self::assertResponseStatusCodeSame(403);
    }

    public function testWhoCanSchedule(): void
    {
        $id = $this->awaitingDefense();
        $payload = ['scheduledAt' => '2026-12-01T10:00', 'location' => 'Sala 1'];

        foreach ([$this->student, $this->evaluator1] as $user) {
            $this->request('PUT', "/api/tcc/$id/defense", $payload, $this->as($user));
            self::assertResponseStatusCodeSame(403);
        }

        // Um professor com vários papéis que não orienta nem coordena este TCC também não
        $professor = UserFactory::new()->withRoles([Role::Advisor, Role::Evaluator])->create();
        $this->request('PUT', "/api/tcc/$id/defense", $payload, $this->as($professor));
        self::assertResponseStatusCodeSame(404);

        $this->request('PUT', "/api/tcc/$id/defense", $payload, $this->as($this->coordination));
        self::assertResponseIsSuccessful();
    }

    public function testScheduleValidation(): void
    {
        $id = $this->awaitingDefense();
        $token = $this->as($this->advisor);

        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2026-11-31T10:00', 'location' => 'Sala'], $token);
        self::assertSame('Data e hora da defesa inválidas.', $this->json()['message']);

        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2026-11-20T10:00'], $token);
        self::assertSame('Informe a sala ou o link da defesa.', $this->json()['message']);

        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2026-11-20T10:00', 'meetingUrl' => 'meet'], $token);
        self::assertSame('Link da defesa inválido.', $this->json()['message']);
    }

    public function testGradeValidation(): void
    {
        $id = $this->awaitingDefense();
        $this->scheduledInThePast($id);

        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 11, 'presentationGrade' => -1], $this->as($this->evaluator1));

        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(
            ['A nota do projeto deve estar entre 0 e 10.', 'A nota da apresentação deve estar entre 0 e 10.'],
            $this->json()['message'],
        );

        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 6.995, 'presentationGrade' => 7.25], $this->as($this->evaluator1));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['As notas podem ter no máximo duas casas decimais.'], $this->json()['message']);
    }

    public function testDecisionOnlyForCoordinationAndOnlyAfterAFailedDefense(): void
    {
        $id = $this->awaitingDefense();

        $this->request('POST', "/api/tcc/$id/decision", ['action' => 'reject', 'comment' => 'x'], $this->as($this->coordination));
        self::assertResponseStatusCodeSame(403);

        $this->scheduledInThePast($id);
        $this->bothGrade($id, [1.0, 1.0], [1.0, 1.0]);
        $this->request('POST', "/api/tcc/$id/decision", ['action' => 'reject', 'comment' => 'x'], $this->as($this->advisor));
        self::assertResponseStatusCodeSame(403);
    }
}
