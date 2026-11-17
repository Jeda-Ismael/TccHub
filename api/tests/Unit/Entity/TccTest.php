<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Keyword;
use App\Entity\Tcc;
use App\Entity\ThematicArea;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TccStatus;
use App\Exception\DomainRuleViolation;
use PHPUnit\Framework\TestCase;

final class TccTest extends TestCase
{
    private User $student;
    private User $advisor;
    private User $evaluator;

    protected function setUp(): void
    {
        $this->student = new User('Ana', 'ana@hubtcc.dev');
        $this->advisor = new User('Olga', 'olga@hubtcc.dev', [Role::Advisor]);
        $this->evaluator = new User('Arthur', 'arthur@hubtcc.dev', [Role::Evaluator]);
    }

    private function tcc(): Tcc
    {
        return new Tcc('  Título  ', '  Resumo  ', $this->student);
    }

    public function testNewTccStartsAsDraftWithTrimmedTexts(): void
    {
        $tcc = $this->tcc();

        self::assertSame('Título', $tcc->getTitle());
        self::assertSame('Resumo', $tcc->getAbstract());
        self::assertSame(TccStatus::Draft, $tcc->getStatus());
        self::assertTrue($tcc->isAuthoredBy($this->student));
        self::assertNull($tcc->getAdvisor());
        self::assertNull($tcc->getCurrentDocument());
        self::assertNull($tcc->getCurrentDefense());
        self::assertNull($tcc->getFinalGrade());
        self::assertFalse($tcc->isPublished());
    }

    public function testOnlyStudentsCanAuthor(): void
    {
        $this->expectExceptionMessage('Só um aluno pode ser autor de um TCC.');

        new Tcc('Título', 'Resumo', $this->advisor);
    }

    public function testTitleAndAbstractCannotBeBlank(): void
    {
        $tcc = $this->tcc();

        try {
            $tcc->changeTitle(' ');
            self::fail('Título vazio deveria ser rejeitado');
        } catch (DomainRuleViolation) {
        }

        $this->expectException(DomainRuleViolation::class);
        $tcc->changeAbstract('');
    }

    public function testAdvisorMustHaveAdvisorRole(): void
    {
        $tcc = $this->tcc();

        $this->expectExceptionMessage('Arthur não tem o papel de Orientador.');

        $tcc->changeAdvisor($this->evaluator);
    }

    public function testAdvisorCanBeChangedOrRemoved(): void
    {
        $tcc = $this->tcc();

        $tcc->changeAdvisor($this->advisor);
        self::assertTrue($tcc->isAdvisedBy($this->advisor));

        $tcc->changeAdvisor(null);
        self::assertNull($tcc->getAdvisor());
        self::assertFalse($tcc->isAdvisedBy($this->advisor));
    }

    public function testAdvisorCannotJoinTheBoardOfTheirOwnStudent(): void
    {
        $professor = new User('Paulo', 'paulo@hubtcc.dev', [Role::Advisor, Role::Evaluator]);
        $tcc = $this->tcc();
        $tcc->changeAdvisor($professor);

        $this->expectExceptionMessage('O orientador não pode fazer parte da banca do próprio orientando.');

        $tcc->addBoardMember($professor);
    }

    public function testBoardMemberCannotBecomeAdvisorOfTheSameTcc(): void
    {
        $professor = new User('Paulo', 'paulo@hubtcc.dev', [Role::Advisor, Role::Evaluator]);
        $tcc = $this->tcc();
        $tcc->addBoardMember($professor);

        $this->expectExceptionMessage('O orientador não pode fazer parte da banca do próprio orientando.');

        $tcc->changeAdvisor($professor);
    }

    public function testBoardMembersMustBeEvaluatorsWithoutDuplicates(): void
    {
        $tcc = $this->tcc();
        $member = $tcc->addBoardMember($this->evaluator);

        self::assertSame($member, $tcc->findBoardMember($this->evaluator));
        self::assertCount(1, $tcc->getBoardMembers());

        try {
            $tcc->addBoardMember($this->advisor);
            self::fail('Usuário sem papel de avaliador deveria ser rejeitado');
        } catch (DomainRuleViolation $e) {
            self::assertSame('Olga não tem o papel de Avaliador.', $e->getMessage());
        }

        $this->expectExceptionMessage('Arthur já faz parte da banca.');
        $tcc->addBoardMember($this->evaluator);
    }

    public function testRemovesBoardMember(): void
    {
        $tcc = $this->tcc();
        $tcc->addBoardMember($this->evaluator);

        $tcc->removeBoardMember($this->evaluator);

        self::assertNull($tcc->findBoardMember($this->evaluator));

        $this->expectExceptionMessage('Arthur não faz parte da banca.');
        $tcc->removeBoardMember($this->evaluator);
    }

    public function testBoardIsLockedFromDefenseOnwards(): void
    {
        foreach ([TccStatus::AwaitingDefense, TccStatus::AwaitingDecision, TccStatus::Approved, TccStatus::Rejected] as $status) {
            $tcc = $this->tcc();
            $tcc->setStatus($status);

            try {
                $tcc->addBoardMember($this->evaluator);
                self::fail(\sprintf('Banca deveria estar travada em %s', $status->value));
            } catch (DomainRuleViolation $e) {
                self::assertSame('A banca não pode ser alterada a partir da etapa de defesa.', $e->getMessage());
            }
        }
    }

    public function testEachUploadCreatesANewDocumentVersion(): void
    {
        $tcc = $this->tcc();

        $first = $tcc->addDocument('tcc/a.pdf', 'v1.pdf', 100, str_repeat('a', 64), $this->student);
        $second = $tcc->addDocument('tcc/b.pdf', 'v2.pdf', 200, str_repeat('b', 64), $this->student);

        self::assertSame(1, $first->getVersion());
        self::assertSame(2, $second->getVersion());
        self::assertSame($second, $tcc->getCurrentDocument());
        self::assertCount(2, $tcc->getDocuments());
    }

    public function testDefenseAttemptsAreSequentialAndOneAtATime(): void
    {
        $tcc = $this->tcc();

        $first = $tcc->openDefense();
        self::assertSame(1, $first->getAttempt());

        try {
            $tcc->openDefense();
            self::fail('Não deveria abrir outra defesa com uma em andamento');
        } catch (DomainRuleViolation) {
        }

        $first->close('5.00', '5.00', '5.00', \App\Enum\DefenseOutcome::Failed);
        $second = $tcc->openDefense();

        self::assertSame(2, $second->getAttempt());
        self::assertSame($second, $tcc->getCurrentDefense());
    }

    public function testReplacesThematicAreasAndKeywordsWithoutDuplicates(): void
    {
        $tcc = $this->tcc();
        $ai = new ThematicArea('IA');
        $web = new Keyword('web');

        $tcc->replaceThematicAreas([$ai, $ai]);
        $tcc->replaceKeywords([$web, $web]);

        self::assertCount(1, $tcc->getThematicAreas());
        self::assertCount(1, $tcc->getKeywords());

        $tcc->replaceThematicAreas([]);
        self::assertCount(0, $tcc->getThematicAreas());
    }

    public function testPublicationNeedsApprovalAndStudentAuthorization(): void
    {
        $tcc = $this->tcc();
        $now = new \DateTimeImmutable();

        try {
            $tcc->publish($now);
            self::fail('TCC não aprovado não pode ser publicado');
        } catch (DomainRuleViolation $e) {
            self::assertSame('Só TCCs aprovados podem ser publicados.', $e->getMessage());
        }

        $tcc->setStatus(TccStatus::Approved);
        try {
            $tcc->publish($now);
            self::fail('Sem autorização do aluno não pode publicar');
        } catch (DomainRuleViolation $e) {
            self::assertSame('O aluno ainda não autorizou a publicação.', $e->getMessage());
        }

        $tcc->authorizePublication(true);
        $tcc->publish($now);
        self::assertTrue($tcc->isPublished());
        self::assertSame($now, $tcc->getPublishedAt());

        $tcc->unpublish();
        self::assertFalse($tcc->isPublished());
    }

    public function testRevokingAuthorizationUnpublishes(): void
    {
        $tcc = $this->tcc();
        $tcc->setStatus(TccStatus::Approved);
        $tcc->authorizePublication(true);
        $tcc->publish(new \DateTimeImmutable());

        $tcc->authorizePublication(false);

        self::assertFalse($tcc->isPublicationAuthorized());
        self::assertFalse($tcc->isPublished());
    }

    public function testRecordsStatusHistoryAndFinalGrade(): void
    {
        $tcc = $this->tcc();

        $entry = $tcc->recordStatusChange(null, TccStatus::Draft, 'create', $this->student, '  TCC criado  ');
        $tcc->recordFinalGrade('8.75');

        self::assertSame([$entry], $tcc->getStatusHistory()->toArray());
        self::assertNull($entry->getFromStatus());
        self::assertSame(TccStatus::Draft, $entry->getToStatus());
        self::assertSame('create', $entry->getTransition());
        self::assertSame('TCC criado', $entry->getComment());
        self::assertSame($this->student, $entry->getChangedBy());
        self::assertSame($tcc, $entry->getTcc());
        self::assertSame('8.75', $tcc->getFinalGrade());
    }

    public function testResubmissionNeedsADocumentNewerThanTheCorrectionRequest(): void
    {
        $tcc = $this->tcc();
        self::assertTrue($tcc->hasDocumentSinceCorrectionsRequested(), 'Antes de qualquer pedido de correção não há exigência');

        $tcc->addDocument('a', 'v1.pdf', 10, str_repeat('a', 64), $this->student);
        $tcc->markCorrectionsRequested();
        self::assertFalse($tcc->hasDocumentSinceCorrectionsRequested());

        $tcc->addDocument('b', 'v2.pdf', 10, str_repeat('b', 64), $this->student);
        self::assertTrue($tcc->hasDocumentSinceCorrectionsRequested());
    }

    public function testAssertCanJoinBoardDoesNotChangeTheBoard(): void
    {
        $tcc = $this->tcc();
        $tcc->assertCanJoinBoard($this->evaluator);

        self::assertCount(0, $tcc->getBoardMembers());
    }
}
