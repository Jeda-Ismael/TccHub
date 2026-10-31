<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\BoardMember;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\EvaluationVerdict;
use App\Enum\Role;
use App\Exception\DomainRuleViolation;
use PHPUnit\Framework\TestCase;

final class BoardMemberTest extends TestCase
{
    private BoardMember $member;
    private User $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new User('Arthur', 'arthur@hubtcc.dev', [Role::Evaluator]);
        $tcc = new Tcc('Título', 'Resumo', new User('Ana', 'ana@hubtcc.dev'));
        $this->member = $tcc->addBoardMember($this->evaluator);
    }

    public function testStartsPending(): void
    {
        self::assertSame(EvaluationVerdict::Pending, $this->member->getVerdict());
        self::assertFalse($this->member->hasVoted());
        self::assertNull($this->member->getComment());
        self::assertSame($this->evaluator, $this->member->getEvaluator());
    }

    public function testApprovesWithOptionalComment(): void
    {
        $this->member->giveVerdict(EvaluationVerdict::Approved, '  ');

        self::assertTrue($this->member->hasVoted());
        self::assertSame(EvaluationVerdict::Approved, $this->member->getVerdict());
        self::assertNull($this->member->getComment());
    }

    public function testCorrectionsRequireAComment(): void
    {
        try {
            $this->member->giveVerdict(EvaluationVerdict::CorrectionsRequested, null);
            self::fail('Correção sem comentário deveria falhar');
        } catch (DomainRuleViolation $e) {
            self::assertSame('Descreva as correções solicitadas no comentário.', $e->getMessage());
        }

        $this->member->giveVerdict(EvaluationVerdict::CorrectionsRequested, ' Revisar a metodologia ');
        self::assertSame('Revisar a metodologia', $this->member->getComment());
    }

    public function testCannotVotePending(): void
    {
        $this->expectException(DomainRuleViolation::class);

        $this->member->giveVerdict(EvaluationVerdict::Pending, null);
    }

    public function testResetForNewRound(): void
    {
        $this->member->giveVerdict(EvaluationVerdict::CorrectionsRequested, 'Ajustar');

        $this->member->resetVerdict();

        self::assertFalse($this->member->hasVoted());
        self::assertNull($this->member->getComment());
    }

    public function testVoteIsFinalWithinTheRound(): void
    {
        $this->member->giveVerdict(EvaluationVerdict::Approved, null);

        $this->expectExceptionMessage('Você já registrou seu parecer nesta rodada e ele não pode ser alterado.');

        $this->member->giveVerdict(EvaluationVerdict::CorrectionsRequested, 'Mudei de ideia');
    }

    public function testCanVoteAgainInANewRound(): void
    {
        $this->member->giveVerdict(EvaluationVerdict::CorrectionsRequested, 'Ajustar');
        $this->member->resetVerdict();

        $this->member->giveVerdict(EvaluationVerdict::Approved, null);

        self::assertSame(EvaluationVerdict::Approved, $this->member->getVerdict());
    }
}
