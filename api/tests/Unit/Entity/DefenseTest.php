<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\BoardMember;
use App\Entity\Defense;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\DefenseOutcome;
use App\Enum\Role;
use App\Exception\DomainRuleViolation;
use PHPUnit\Framework\TestCase;

final class DefenseTest extends TestCase
{
    private Tcc $tcc;
    private BoardMember $member;
    private User $advisor;

    protected function setUp(): void
    {
        $this->advisor = new User('Olga', 'olga@hubtcc.dev', [Role::Advisor]);
        $this->tcc = new Tcc('Título', 'Resumo', new User('Ana', 'ana@hubtcc.dev'));
        $this->member = $this->tcc->addBoardMember(new User('Arthur', 'arthur@hubtcc.dev', [Role::Evaluator]));
    }

    public function testNewDefenseIsPendingAndUnscheduled(): void
    {
        $defense = $this->tcc->openDefense();

        self::assertSame($this->tcc, $defense->getTcc());
        self::assertFalse($defense->isScheduled());
        self::assertFalse($defense->isClosed());
        self::assertFalse($defense->hasStartedAt(new \DateTimeImmutable()));
        self::assertSame(DefenseOutcome::Pending, $defense->getOutcome());
        self::assertNull($defense->getScheduledBy());
        self::assertCount(0, $defense->getGrades());
    }

    public function testAttemptStartsAtOne(): void
    {
        $this->expectException(DomainRuleViolation::class);

        new Defense($this->tcc, 0);
    }

    public function testSchedulesWithRoomOrLink(): void
    {
        $defense = $this->tcc->openDefense();
        $at = new \DateTimeImmutable('2026-12-01 14:00');

        $defense->schedule($at, ' Sala 101 ', ' ', $this->advisor);

        self::assertTrue($defense->isScheduled());
        self::assertSame($at, $defense->getScheduledAt());
        self::assertSame('Sala 101', $defense->getLocation());
        self::assertNull($defense->getMeetingUrl());
        self::assertSame($this->advisor, $defense->getScheduledBy());
        self::assertTrue($defense->hasStartedAt(new \DateTimeImmutable('2026-12-01 14:00')));
        self::assertFalse($defense->hasStartedAt(new \DateTimeImmutable('2026-12-01 13:59')));

        $defense->schedule($at, null, 'https://meet.example.com/abc', $this->advisor);
        self::assertNull($defense->getLocation());
        self::assertSame('https://meet.example.com/abc', $defense->getMeetingUrl());
    }

    public function testSchedulingNeedsRoomOrValidLink(): void
    {
        $defense = $this->tcc->openDefense();
        $at = new \DateTimeImmutable('+1 week');

        try {
            $defense->schedule($at, '', null, $this->advisor);
            self::fail('Sem sala nem link deveria falhar');
        } catch (DomainRuleViolation $e) {
            self::assertSame('Informe a sala ou o link da defesa.', $e->getMessage());
        }

        $this->expectExceptionMessage('Link da defesa inválido.');
        $defense->schedule($at, null, 'meet-abc', $this->advisor);
    }

    public function testCannotRescheduleAfterGradesStarted(): void
    {
        $defense = $this->tcc->openDefense();
        $defense->schedule(new \DateTimeImmutable('-1 hour'), 'Sala 1', null, $this->advisor);
        $defense->grade($this->member, 8, 9, null);

        $this->expectExceptionMessage('Não é possível reagendar: a banca já começou a lançar notas.');

        $defense->schedule(new \DateTimeImmutable('+1 day'), 'Sala 2', null, $this->advisor);
    }

    public function testGradingTwiceUpdatesTheSameGrade(): void
    {
        $defense = $this->tcc->openDefense();

        $first = $defense->grade($this->member, 7, '8.5', ' Bom trabalho ');
        $second = $defense->grade($this->member, '9', 9.25, '');

        self::assertSame($first, $second);
        self::assertCount(1, $defense->getGrades());
        self::assertSame('9.00', $second->getProjectGrade());
        self::assertSame('9.25', $second->getPresentationGrade());
        self::assertNull($second->getComment());
        self::assertSame($defense, $second->getDefense());
        self::assertSame($this->member, $second->getBoardMember());
        self::assertSame($first, $defense->findGradeBy($this->member));
    }

    public function testRejectsGradeFromMemberOfAnotherTcc(): void
    {
        $other = new Tcc('Outro', 'Resumo', new User('Bruno', 'bruno@hubtcc.dev'));
        $outsider = $other->addBoardMember(new User('Alice', 'alice@hubtcc.dev', [Role::Evaluator]));
        $defense = $this->tcc->openDefense();

        $this->expectExceptionMessage('Este avaliador não faz parte da banca deste TCC.');

        $defense->grade($outsider, 8, 8, null);
    }

    public function testClosingRecordsResultAndBlocksChanges(): void
    {
        $defense = $this->tcc->openDefense();
        $defense->grade($this->member, 8, 9, null);

        $defense->close('8.00', '9.00', '8.50', DefenseOutcome::Passed);

        self::assertTrue($defense->isClosed());
        self::assertSame('8.00', $defense->getProjectAverage());
        self::assertSame('9.00', $defense->getPresentationAverage());
        self::assertSame('8.50', $defense->getFinalGrade());
        self::assertSame(DefenseOutcome::Passed, $defense->getOutcome());

        foreach ([
            fn () => $defense->grade($this->member, 10, 10, null),
            fn () => $defense->schedule(new \DateTimeImmutable(), 'Sala', null, $this->advisor),
            static fn () => $defense->close('1.00', '1.00', '1.00', DefenseOutcome::Failed),
        ] as $action) {
            try {
                $action();
                self::fail('Defesa encerrada não deveria aceitar alterações');
            } catch (DomainRuleViolation $e) {
                self::assertSame('Esta defesa já foi encerrada.', $e->getMessage());
            }
        }
    }

    public function testCannotCloseWithPendingOutcome(): void
    {
        $defense = $this->tcc->openDefense();

        $this->expectException(DomainRuleViolation::class);

        $defense->close('8.00', '8.00', '8.00', DefenseOutcome::Pending);
    }
}
