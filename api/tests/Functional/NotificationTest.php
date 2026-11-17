<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Fase 6: avisos do sininho gerados pelo fluxo e a API para lê-los.
 */
final class NotificationTest extends ApiTestCase
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

    private function id(User $user): string
    {
        return $user->getId()->toRfc4122();
    }

    /** @return list<string> tipos recebidos pelo usuário, do mais antigo ao mais novo */
    private function typesFor(User $user): array
    {
        $this->request('GET', '/api/notifications', token: $this->loginAs($user));
        self::assertResponseIsSuccessful();

        return array_reverse(array_column($this->json()['items'], 'type'));
    }

    private function createAndSubmit(): string
    {
        $token = $this->loginAs($this->student);
        $this->request('POST', '/api/tcc', ['title' => 'Detecção de fraudes', 'abstract' => 'Resumo', 'advisorId' => $this->id($this->advisor)], $token);
        $id = $this->json()['id'];
        $path = (string) tempnam(sys_get_temp_dir(), 'tcc');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $this->client->request('POST', "/api/tcc/$id/document", [], ['file' => new UploadedFile($path, 'tcc.pdf', 'application/pdf', null, true)], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->request('POST', "/api/tcc/$id/submit", token: $token);
        self::assertResponseIsSuccessful();

        return $id;
    }

    private function toDefense(string $id): void
    {
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $this->loginAs($this->coordination));
        $advisorToken = $this->loginAs($this->advisor);
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $advisorToken);
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->loginAs($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->loginAs($this->evaluator2));
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $advisorToken);
        self::assertSame('AWAITING_DEFENSE', $this->json()['status']);
    }

    private function gradeBoth(string $id, float $grade): void
    {
        $at = (new \DateTimeImmutable('-1 hour'))->format(\DATE_ATOM);
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => $at, 'location' => 'Sala 101'], $this->loginAs($this->advisor));
        foreach ([$this->evaluator1, $this->evaluator2] as $evaluator) {
            $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => $grade, 'presentationGrade' => $grade], $this->loginAs($evaluator));
            self::assertResponseIsSuccessful();
        }
    }

    public function testEachPersonIsNotifiedAlongTheWayToApproval(): void
    {
        $id = $this->createAndSubmit();
        $this->toDefense($id);
        $this->gradeBoth($id, 9);

        // Cada um abre o TCC com o papel que tem nele
        self::assertSame('EVALUATOR', $this->lastFor($this->evaluator1)['as']);
        self::assertSame('ADVISOR', $this->lastFor($this->advisor)['as']);

        // O "Agende a defesa" não aparece: foi o próprio orientador que aprovou para a defesa
        self::assertSame(['TCC_SUBMITTED', 'FINAL_REVIEW_PENDING', 'TCC_APPROVED'], $this->typesFor($this->advisor));
        self::assertSame(['EVALUATION_OPEN', 'DEFENSE_PENDING', 'DEFENSE_SCHEDULED', 'TCC_APPROVED'], $this->typesFor($this->student));
        // Quem lançou a última nota não é avisado do resultado que ele mesmo gerou
        self::assertSame(['BOARD_ASSIGNED', 'EVALUATION_OPEN', 'DEFENSE_SCHEDULED'], $this->typesFor($this->evaluator1));
        self::assertSame([], $this->typesFor($this->coordination));

        $this->request('GET', '/api/notifications', token: $this->loginAs($this->student));
        $approved = $this->json()['items'][0];
        self::assertSame('TCC aprovado', $approved['title']);
        self::assertSame('"Detecção de fraudes" foi aprovado com média final 9,00.', $approved['body']);
        self::assertSame($id, $approved['tccId']);
        self::assertSame('STUDENT', $approved['as']);
        self::assertFalse($approved['read']);
        self::assertSame(4, $this->json()['unreadCount']);
    }

    public function testCorrectionsFromTheBoardPointToTheReviews(): void
    {
        $id = $this->createAndSubmit();
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $this->loginAs($this->coordination));
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $this->loginAs($this->advisor));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->loginAs($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'CORRECTIONS_REQUESTED', 'comment' => 'Revise a metodologia'], $this->loginAs($this->evaluator2));
        self::assertSame('CORRECTIONS_REQUESTED', $this->json()['status']);

        $latest = $this->lastFor($this->student);
        self::assertSame('CORRECTIONS_REQUESTED', $latest['type']);
        self::assertSame('A banca terminou a avaliação e pediu correções. Veja os pareceres, envie a versão corrigida do PDF e reenvie o TCC.', $latest['body']);
    }

    public function testCorrectionsFromTheAdvisorCarryTheComment(): void
    {
        $id = $this->createAndSubmit();
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $this->loginAs($this->coordination));
        $advisorToken = $this->loginAs($this->advisor);
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $advisorToken);
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->loginAs($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->loginAs($this->evaluator2));
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => false, 'comment' => 'Ajuste as referências'], $advisorToken);
        self::assertSame('CORRECTIONS_REQUESTED', $this->json()['status']);

        self::assertSame('Comentário do orientador: Ajuste as referências', $this->lastFor($this->student)['body']);
    }

    /** O coordenador que estava na banca e deu a última nota também precisa saber que deve decidir. */
    public function testCoordinatorOnTheBoardWhoGradedLastIsAskedToDecide(): void
    {
        $this->evaluator2 = UserFactory::new()->withRoles([Role::Evaluator, Role::Coordination])->create(['name' => 'Paulo']);
        $this->coordination = $this->evaluator2;
        $id = $this->createAndSubmit();
        $this->toDefense($id);
        $this->gradeBoth($id, 3);

        $last = $this->lastFor($this->evaluator2);
        self::assertSame('DECISION_PENDING', $last['type']);
        self::assertSame('COORDINATION', $last['as']);
        // Nos outros avisos, ele abre o TCC como avaliador
        $this->request('GET', '/api/notifications', token: $this->loginAs($this->evaluator2));
        self::assertSame('EVALUATOR', $this->json()['items'][1]['as']);
    }

    public function testCoordinationIsAskedToDecideAfterAFailedDefense(): void
    {
        $otherCoordination = UserFactory::new()->coordination()->create();
        $id = $this->createAndSubmit();
        $this->toDefense($id);
        $this->gradeBoth($id, 3);

        foreach ([$this->coordination, $otherCoordination] as $user) {
            self::assertSame(['DECISION_PENDING'], $this->typesFor($user));
        }
        self::assertSame('COORDINATION', $this->lastFor($this->coordination)['as']);

        $this->request('POST', "/api/tcc/$id/decision", ['action' => 'new_defense', 'comment' => 'Pode tentar de novo'], $this->loginAs($this->coordination));
        self::assertSame('Nova defesa liberada', $this->lastFor($this->student)['title']);
        // A outra coordenadora não fez nada, então continua sem novos avisos
        self::assertSame(['DECISION_PENDING'], $this->typesFor($otherCoordination));
    }

    public function testRejectionTellsTheReason(): void
    {
        $id = $this->createAndSubmit();
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => false, 'comment' => 'Fora do tema'], $this->loginAs($this->advisor));
        self::assertSame('REJECTED', $this->json()['status']);

        $last = $this->lastFor($this->student);
        self::assertSame('TCC_REJECTED', $last['type']);
        self::assertStringContainsString('Fora do tema', $last['body']);
    }

    public function testReschedulingNotifiesAgain(): void
    {
        $id = $this->createAndSubmit();
        $this->toDefense($id);
        $token = $this->loginAs($this->coordination);
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2026-12-01T10:00', 'location' => 'Sala 1'], $token);
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => '2026-12-02T14:30', 'meetingUrl' => 'https://meet.example.com/x'], $token);

        $last = $this->lastFor($this->advisor);
        self::assertSame('Defesa reagendada', $last['title']);
        self::assertSame('"Detecção de fraudes": 02/12/2026 às 14:30 (https://meet.example.com/x).', $last['body']);
    }

    public function testAddingSomeoneToTheBoardDuringEvaluation(): void
    {
        $evaluator3 = UserFactory::new()->evaluator()->create();
        $id = $this->createAndSubmit();
        $coordToken = $this->loginAs($this->coordination);
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $coordToken);
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $this->loginAs($this->advisor));
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2), $this->id($evaluator3)]], $coordToken);
        self::assertResponseIsSuccessful();

        $last = $this->lastFor($evaluator3);
        self::assertSame('BOARD_ASSIGNED', $last['type']);
        self::assertStringContainsString('aguardando o seu parecer', $last['body']);
        // Quem já estava não recebe de novo
        self::assertSame(['BOARD_ASSIGNED', 'EVALUATION_OPEN'], $this->typesFor($this->evaluator1));
    }

    public function testReadOneAndReadAll(): void
    {
        $this->createAndSubmit();
        $this->request('PUT', '/api/tcc/'.$this->lastFor($this->advisor)['tccId'].'/evaluators', ['evaluatorIds' => [$this->id($this->evaluator1)]], $this->loginAs($this->coordination));
        $token = $this->loginAs($this->evaluator1);
        $this->request('GET', '/api/notifications/unread-count', token: $token);
        self::assertSame(['unreadCount' => 1], $this->json());

        $advisorToken = $this->loginAs($this->advisor);
        $notificationId = $this->lastFor($this->advisor)['id'];

        // Outra pessoa não marca o aviso de ninguém
        $this->request('POST', "/api/notifications/$notificationId/read", token: $token);
        self::assertResponseStatusCodeSame(404);

        $this->request('POST', "/api/notifications/$notificationId/read", token: $advisorToken);
        self::assertSame(['unreadCount' => 0], $this->json());
        $this->request('GET', '/api/notifications?unread=1', token: $advisorToken);
        self::assertSame([], $this->json()['items']);
        $this->request('GET', '/api/notifications', token: $advisorToken);
        self::assertTrue($this->json()['items'][0]['read']);

        $this->request('POST', '/api/notifications/read-all', token: $token);
        $this->request('GET', '/api/notifications/unread-count', token: $token);
        self::assertSame(['unreadCount' => 0], $this->json());
    }

    public function testListIsLimitedAndRequiresLogin(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = $em->find(User::class, $this->student->getId());
        \assert($user instanceof User);
        for ($i = 0; $i < 35; ++$i) {
            $em->persist(new Notification($user, NotificationType::TccSubmitted, "Aviso $i", 'Texto'));
        }
        $em->flush();

        $this->request('GET', '/api/notifications', token: $this->loginAs($this->student));
        self::assertCount(30, $this->json()['items']);
        self::assertSame(35, $this->json()['unreadCount']);

        $this->request('GET', '/api/notifications');
        self::assertResponseStatusCodeSame(401);
    }

    /** @return array<string, mixed> */
    private function lastFor(User $user): array
    {
        $this->request('GET', '/api/notifications', token: $this->loginAs($user));

        return $this->json()['items'][0];
    }
}
