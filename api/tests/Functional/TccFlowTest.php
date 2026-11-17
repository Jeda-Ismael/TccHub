<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Factory\KeywordFactory;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\ThematicAreaFactory;
use App\Factory\UserFactory;
use App\Repository\TccRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * O caminho do TCC até a defesa, do jeito que as pessoas usam o sistema.
 */
final class TccFlowTest extends ApiTestCase
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

    private function pdf(string $content = 'versão'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tcc');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% $content\ntrailer << /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($path, 'meu-tcc.pdf', 'application/pdf', null, true);
    }

    private function upload(string $tccId, string $token, ?UploadedFile $file = null): void
    {
        $this->client->request('POST', "/api/tcc/$tccId/document", [], ['file' => $file ?? $this->pdf()], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    /** Cria um TCC com orientador e PDF, pronto para submeter. */
    private function draft(): string
    {
        $token = $this->as($this->student);
        $this->request('POST', '/api/tcc', ['title' => 'Detecção de fraudes', 'abstract' => 'Resumo', 'advisorId' => $this->id($this->advisor)], $token);
        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'];
        $this->upload($id, $token);
        self::assertResponseStatusCodeSame(201);

        return $id;
    }

    /** Leva o TCC até "em avaliação pela banca" com os dois avaliadores. */
    private function inEvaluation(): string
    {
        $id = $this->draft();
        $this->request('POST', "/api/tcc/$id/submit", token: $this->as($this->student));
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $this->as($this->coordination));
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $this->as($this->advisor));
        self::assertSame('SENT_TO_EVALUATION', $this->json()['status']);

        return $id;
    }

    public function testFullCycleWithCorrectionsUntilDefense(): void
    {
        $id = $this->draft();

        // Aluno submete
        $this->request('POST', "/api/tcc/$id/submit", token: $this->as($this->student));
        self::assertResponseIsSuccessful();
        self::assertSame('SUBMITTED', $this->json()['status']);

        // Coordenação monta a banca; orientador aprova o envio
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $this->as($this->coordination));
        self::assertResponseIsSuccessful();
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true, 'comment' => 'Pronto para a banca'], $this->as($this->advisor));
        self::assertSame('SENT_TO_EVALUATION', $this->json()['status']);

        // Primeiro parecer não muda o status
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator1));
        self::assertResponseIsSuccessful();
        self::assertSame('SENT_TO_EVALUATION', $this->json()['status']);

        // Segundo pede correção: volta para o aluno
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'CORRECTIONS_REQUESTED', 'comment' => 'Revisar a metodologia'], $this->as($this->evaluator2));
        self::assertSame('CORRECTIONS_REQUESTED', $this->json()['status']);

        // Aluno vê o comentário, envia a versão 2 e reenvia
        $studentToken = $this->as($this->student);
        $this->request('GET', "/api/tcc/$id", token: $studentToken);
        self::assertContains('Revisar a metodologia', array_column($this->json()['board'], 'comment'));
        $this->upload($id, $studentToken, $this->pdf('v2'));
        self::assertSame(2, $this->json()['document']['version']);
        self::assertCount(2, $this->json()['documents']);
        $this->request('POST', "/api/tcc/$id/submit", token: $studentToken);
        self::assertSame('RESUBMITTED', $this->json()['status']);

        // Nova rodada: pareceres zerados
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $this->as($this->advisor));
        self::assertSame(['PENDING', 'PENDING'], array_column($this->json()['board'], 'verdict'));

        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED', 'comment' => 'Muito bom'], $this->as($this->evaluator2));
        self::assertSame('ADVISOR_FINAL_REVIEW', $this->json()['status']);

        // Revisão final do orientador: segue para a defesa
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $this->as($this->advisor));
        self::assertSame('AWAITING_DEFENSE', $this->json()['status']);

        self::assertSame(
            ['create', 'submit', 'advisor_approve', 'board_request_corrections', 'resubmit', 'advisor_approve', 'board_approve', 'final_approve'],
            array_column($this->json()['statusHistory'], 'transition'),
        );
        $tcc = self::getContainer()->get(TccRepository::class)->find(Uuid::fromString($id));
        self::assertSame(1, $tcc?->getCurrentDefense()?->getAttempt(), 'Entrar em AWAITING_DEFENSE abre a 1ª tentativa de defesa');
    }

    public function testCreationNeedsAnOpenSubmissionPeriod(): void
    {
        self::getContainer()->get(Connection::class)->executeStatement("DELETE FROM submission_periods WHERE type = 'SUBMISSION'");

        $this->request('POST', '/api/tcc', ['title' => 'T', 'abstract' => 'R'], $this->as($this->student));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Não há prazo de submissão de TCC aberto no momento.', $this->json()['message']);
    }

    public function testOnlyStudentsCreateTccs(): void
    {
        $this->request('POST', '/api/tcc', ['title' => 'T', 'abstract' => 'R'], $this->as($this->advisor));

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreationValidatesAndLinksCatalogs(): void
    {
        $token = $this->as($this->student);
        $area = ThematicAreaFactory::createOne(['name' => 'IA']);
        $keyword = KeywordFactory::createOne(['name' => 'fraude']);

        $this->request('POST', '/api/tcc', ['title' => '', 'abstract' => ''], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['Informe o título.', 'Informe o resumo.'], $this->json()['message']);

        $this->request('POST', '/api/tcc', ['title' => 'T', 'abstract' => 'R', 'advisorId' => $this->id($this->evaluator1)], $token);
        self::assertSame('Orientador não encontrado.', $this->json()['message']);

        $this->request('POST', '/api/tcc', [
            'title' => 'T', 'abstract' => 'R',
            'thematicAreaIds' => [$area->getId()->toRfc4122()],
            'keywordIds' => [$keyword->getId()->toRfc4122()],
        ], $token);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('IA', $this->json()['thematicAreas'][0]['name']);
        self::assertSame('fraude', $this->json()['keywords'][0]['name']);
        self::assertSame('DRAFT', $this->json()['status']);
        self::assertTrue($this->json()['permissions']['submit']);
    }

    public function testSubmitExplainsWhatIsMissing(): void
    {
        $token = $this->as($this->student);
        $this->request('POST', '/api/tcc', ['title' => 'T', 'abstract' => 'R'], $token);
        $id = $this->json()['id'];

        $this->request('POST', "/api/tcc/$id/submit", token: $token);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('Envie o PDF do TCC antes de submeter. Escolha um orientador antes de submeter.', $this->json()['message']);
    }

    public function testPdfIsCheckedByContentAndDownloadedBySignedLink(): void
    {
        $id = $this->draft();
        $token = $this->as($this->student);

        $fake = $this->pdf();
        file_put_contents($fake->getPathname(), 'isto não é um pdf');
        $this->upload($id, $token, $fake);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Envie um arquivo PDF.', $this->json()['message']);

        $this->request('GET', "/api/tcc/$id", token: $token);
        $link = $this->json()['document']['downloadUrl'];
        self::assertStringContainsString('_hash=', $link);

        // O link funciona sem token (é aberto num <a href>)
        $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');

        // Sem assinatura, ou com a versão trocada, não funciona
        $this->client->request('GET', "/api/tcc/$id/documents/1/download");
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Link de download inválido ou expirado. Abra o TCC de novo para gerar outro.', $this->json()['message']);
        $this->client->request('GET', str_replace('/documents/1/', '/documents/2/', $link));
        self::assertResponseStatusCodeSame(403);
    }

    public function testVeryLongFileNameIsShortenedNotAnError(): void
    {
        $id = $this->draft();
        $file = $this->pdf();
        $longName = str_repeat('a', 400).'.pdf';

        $this->upload($id, $this->as($this->student), new UploadedFile($file->getPathname(), $longName, 'application/pdf', null, true));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(255, mb_strlen($this->json()['document']['originalName']));
        self::assertStringEndsWith('.pdf', $this->json()['document']['originalName']);
    }

    public function testOutsidersCannotSeeOrTouchTheTcc(): void
    {
        $id = $this->draft();
        $otherStudent = UserFactory::new()->student()->create();
        $otherAdvisor = UserFactory::new()->advisor()->create();

        foreach ([$otherStudent, $otherAdvisor, $this->evaluator1] as $outsider) {
            $this->request('GET', "/api/tcc/$id", token: $this->as($outsider));
            self::assertResponseStatusCodeSame(404, 'Quem não participa do TCC nem sabe que ele existe');
        }

        // O orientador vê, mas não edita o rascunho do aluno
        $advisorToken = $this->as($this->advisor);
        $this->request('GET', "/api/tcc/$id", token: $advisorToken);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['permissions']['edit']);
        $this->request('PATCH', "/api/tcc/$id", ['title' => 'Outro'], $advisorToken);
        self::assertResponseStatusCodeSame(403);
    }

    public function testStudentCannotEditWhileUnderReview(): void
    {
        $id = $this->draft();
        $token = $this->as($this->student);
        $this->request('POST', "/api/tcc/$id/submit", token: $token);

        $this->request('PATCH', "/api/tcc/$id", ['title' => 'Novo título'], $token);
        self::assertResponseStatusCodeSame(403);

        $this->upload($id, $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdvisorApprovalNeedsAFullBoard(): void
    {
        $id = $this->draft();
        $this->request('POST', "/api/tcc/$id/submit", token: $this->as($this->student));
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1)]], $this->as($this->coordination));

        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $this->as($this->advisor));

        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('A banca precisa ter de 2 a 3 avaliadores', $this->json()['message']);
    }

    public function testBoardRules(): void
    {
        $id = $this->draft();
        $token = $this->as($this->coordination);
        $professor = UserFactory::new()->withRoles([Role::Advisor, Role::Evaluator])->create();
        $this->request('PATCH', "/api/tcc/$id", ['advisorId' => $this->id($professor)], $this->as($this->student));

        // O orientador não entra na banca do próprio orientando
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($professor)]], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('O orientador não pode fazer parte da banca do próprio orientando.', $this->json()['message']);

        // Só quem tem papel de avaliador
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->advisor)]], $token);
        self::assertSame('Olga não tem o papel de Avaliador.', $this->json()['message']);

        // No máximo 3 (configuração padrão)
        $extra = UserFactory::new()->evaluator()->many(2)->create();
        $ids = [$this->id($this->evaluator1), $this->id($this->evaluator2), ...array_map(fn ($u) => $this->id($u), $extra)];
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => $ids], $token);
        self::assertSame('A banca pode ter no máximo 3 avaliadores.', $this->json()['message']);

        // Só a coordenação monta a banca (nem o orientador do TCC)
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => []], $this->as($professor));
        self::assertResponseStatusCodeSame(403);
    }

    public function testEvaluationIsBlindUntilYouVote(): void
    {
        $id = $this->inEvaluation();
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'CORRECTIONS_REQUESTED', 'comment' => 'Faltam referências'], $this->as($this->evaluator1));

        // Quem ainda não votou não vê o voto do colega
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->evaluator2));
        $board = array_column($this->json()['board'], null, 'isMe');
        self::assertSame('PENDING', $board[0]['verdict']);
        self::assertNull($board[0]['comment']);
        self::assertTrue($this->json()['permissions']['evaluate']);

        // O aluno não vê pareceres parciais
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->student));
        self::assertSame(['PENDING', 'PENDING'], array_column($this->json()['board'], 'verdict'));

        // O orientador e a coordenação acompanham
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->coordination));
        self::assertContains('CORRECTIONS_REQUESTED', array_column($this->json()['board'], 'verdict'));
    }

    public function testEvaluationNeedsAnOpenPeriod(): void
    {
        $id = $this->inEvaluation();
        self::getContainer()->get(Connection::class)->executeStatement("DELETE FROM submission_periods WHERE type = 'EVALUATION'");

        // A tela não oferece o formulário fora do prazo
        $this->request('GET', "/api/tcc/$id", token: $this->as($this->evaluator1));
        self::assertFalse($this->json()['evaluationPeriodOpen']);
        self::assertFalse($this->json()['permissions']['evaluate']);

        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator1));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Não há prazo de avaliação aberto no momento.', $this->json()['message']);
    }

    public function testCorrectionsNeedAComment(): void
    {
        $id = $this->inEvaluation();

        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'CORRECTIONS_REQUESTED'], $this->as($this->evaluator1));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Descreva as correções solicitadas no comentário.', $this->json()['message']);
    }

    public function testRemovingThePendingEvaluatorClosesTheRound(): void
    {
        $third = UserFactory::new()->evaluator()->create(['name' => 'Bia']);
        $id = $this->draft();
        $this->request('POST', "/api/tcc/$id/submit", token: $this->as($this->student));
        $coordination = $this->as($this->coordination);
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2), $this->id($third)]], $coordination);
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $this->as($this->advisor));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator2));

        // A coordenação tira da banca quem não votou: os pareceres que restam decidem
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $coordination);

        self::assertResponseIsSuccessful();
        self::assertSame('ADVISOR_FINAL_REVIEW', $this->json()['status']);
    }

    public function testBoardKeepsTheMinimumSizeDuringEvaluation(): void
    {
        $id = $this->inEvaluation();

        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1)]], $this->as($this->coordination));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Com o TCC em avaliação, a banca precisa manter pelo menos 2 avaliadores.', $this->json()['message']);
    }

    public function testVoteCannotBeChangedAfterSeeingTheOthers(): void
    {
        $id = $this->inEvaluation();
        $token = $this->as($this->evaluator1);
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $token);
        self::assertFalse($this->json()['permissions']['evaluate']);

        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'CORRECTIONS_REQUESTED', 'comment' => 'Mudei de ideia'], $token);

        self::assertResponseStatusCodeSame(403);
    }

    public function testResubmissionNeedsANewPdf(): void
    {
        $id = $this->inEvaluation();
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'CORRECTIONS_REQUESTED', 'comment' => 'Ajustar'], $this->as($this->evaluator2));
        $token = $this->as($this->student);

        $this->request('POST', "/api/tcc/$id/submit", token: $token);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Envie a versão corrigida do PDF antes de reenviar.', $this->json()['message']);

        $this->upload($id, $token, $this->pdf('corrigido'));
        $this->request('POST', "/api/tcc/$id/submit", token: $token);
        self::assertSame('RESUBMITTED', $this->json()['status']);
    }

    public function testOneActiveTccPerStudent(): void
    {
        $this->draft();

        $this->request('POST', '/api/tcc', ['title' => 'Outro', 'abstract' => 'R'], $this->as($this->student));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Você já tem um TCC em andamento.', $this->json()['message']);
    }

    public function testFailedActionDoesNotBreakTheNextOne(): void
    {
        $id = $this->draft();
        $token = $this->as($this->coordination);

        // Uma regra barrada não pode deixar a banca pela metade nem travar o sistema
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->advisor)]], $token);
        self::assertResponseStatusCodeSame(422);
        $this->request('GET', "/api/tcc/$id", token: $token);
        self::assertSame([], $this->json()['board']);

        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $token);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->json()['board']);
    }

    public function testAdvisorRejectionNeedsAReasonAndEndsTheTcc(): void
    {
        $id = $this->draft();
        $this->request('POST', "/api/tcc/$id/submit", token: $this->as($this->student));
        $token = $this->as($this->advisor);

        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => false], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Explique o motivo no comentário.', $this->json()['message']);

        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => false, 'comment' => 'Tema fora do escopo do curso'], $token);
        self::assertSame('REJECTED', $this->json()['status']);
        self::assertFalse($this->json()['permissions']['advisorReview']);
    }

    public function testFinalReviewCanSendBackForCorrections(): void
    {
        $id = $this->inEvaluation();
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->as($this->evaluator2));

        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => false, 'comment' => 'Ajustar a conclusão'], $this->as($this->advisor));

        self::assertSame('CORRECTIONS_REQUESTED', $this->json()['status']);
        self::assertSame('Ajustar a conclusão', array_reverse($this->json()['statusHistory'])[0]['comment']);
    }

    public function testListsByTheChosenRole(): void
    {
        $id = $this->inEvaluation();
        $professor = UserFactory::new()->withRoles([Role::Advisor, Role::Evaluator, Role::Coordination])->create();

        $this->request('GET', '/api/tcc?as=EVALUATOR', token: $this->as($this->evaluator1));
        self::assertSame([$id], array_column($this->json(), 'id'));

        $this->request('GET', '/api/tcc?as=STUDENT', token: $this->as($this->student));
        self::assertSame([$id], array_column($this->json(), 'id'));

        $token = $this->as($professor);
        $this->request('GET', '/api/tcc?as=ADVISOR', token: $token);
        self::assertSame([], $this->json());
        $this->request('GET', '/api/tcc?as=COORDINATION', token: $token);
        self::assertSame([$id], array_column($this->json(), 'id'));
        $this->request('GET', '/api/tcc?as=COORDINATION&status=DRAFT', token: $token);
        self::assertSame([], $this->json());

        // Não dá para listar com um papel que não tem
        $this->request('GET', '/api/tcc?as=COORDINATION', token: $this->as($this->student));
        self::assertResponseStatusCodeSame(403);
    }

    public function testCannotSubmitTwice(): void
    {
        $id = $this->draft();
        $token = $this->as($this->student);
        $this->request('POST', "/api/tcc/$id/submit", token: $token);

        $this->request('POST', "/api/tcc/$id/submit", token: $token);

        self::assertResponseStatusCodeSame(403);
    }
}
