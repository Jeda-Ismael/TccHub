<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Defense;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\DefenseOutcome;
use App\Enum\TccStatus;
use App\Exception\DomainRuleViolation;
use App\Exception\TransitionNotAllowed;
use App\Repository\SettingsRepository;
use App\Util\Grade;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Defesa do TCC (Fase 5): agendamento, notas da banca, resultado e decisão da
 * coordenação quando a média fica abaixo do mínimo. Ver docs/ARQUITETURA.md §7.
 */
final class DefenseService
{
    public function __construct(
        #[Target('tcc')] private readonly WorkflowInterface $workflow,
        private readonly SettingsRepository $settings,
        private readonly TccLocker $locker,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    /** Agenda ou reagenda (só enquanto ninguém lançou nota). */
    public function schedule(Tcc $tcc, \DateTimeImmutable $at, ?string $location, ?string $meetingUrl, User $by): void
    {
        $this->locker->run($tcc, function () use ($tcc, $at, $location, $meetingUrl, $by): void {
            $this->currentDefense($tcc)->schedule($at, $location, $meetingUrl, $by);
        });
    }

    /**
     * Nota de um avaliador, depois do horário da defesa. Pode ser corrigida até
     * todos lançarem; quando o último lança, a defesa fecha e o resultado sai.
     */
    public function grade(Tcc $tcc, User $evaluator, string|float $projectGrade, string|float $presentationGrade, ?string $comment): void
    {
        $this->locker->run($tcc, function () use ($tcc, $evaluator, $projectGrade, $presentationGrade, $comment): void {
            $defense = $this->currentDefense($tcc);
            if (!$defense->isScheduled()) {
                throw new DomainRuleViolation('A defesa ainda não foi agendada.');
            }
            if (!$defense->hasStartedAt($this->clock->now())) {
                throw new DomainRuleViolation(\sprintf('As notas podem ser lançadas a partir do horário da defesa (%s).', $defense->getScheduledAt()?->setTimezone(new \DateTimeZone($this->timezone))->format('d/m/Y \à\s H:i')));
            }

            $member = $tcc->findBoardMember($evaluator)
                ?? throw new DomainRuleViolation('Você não faz parte da banca deste TCC.');
            $defense->grade($member, $projectGrade, $presentationGrade, $comment);

            $this->closeIfComplete($tcc, $defense);
        });
    }

    /**
     * Média abaixo do mínimo: a coordenação permite uma nova defesa (abre a
     * tentativa seguinte) ou reprova. O comentário é obrigatório nos dois casos.
     */
    public function decide(Tcc $tcc, bool $allowNewDefense, ?string $comment): void
    {
        $comment = null === $comment ? '' : trim($comment);
        if ('' === $comment) {
            throw new DomainRuleViolation('Explique a decisão no comentário.');
        }

        $this->locker->run($tcc, function () use ($tcc, $allowNewDefense, $comment): void {
            $this->apply($tcc, $allowNewDefense ? 'allow_new_defense' : 'coordination_reject', $comment);
        });
    }

    /** Quando toda a banca lançou nota: calcula as médias e aprova ou manda para a decisão. */
    private function closeIfComplete(Tcc $tcc, Defense $defense): void
    {
        $members = $tcc->getBoardMembers();
        foreach ($members as $member) {
            if (null === $defense->findGradeBy($member)) {
                return;
            }
        }

        $grades = $defense->getGrades()->getValues();
        if ([] === $grades) {
            return;
        }

        $projectGrades = array_map(static fn ($g): string => $g->getProjectGrade(), $grades);
        $presentationGrades = array_map(static fn ($g): string => $g->getPresentationGrade(), $grades);
        $project = Grade::average($projectGrades);
        $presentation = Grade::average($presentationGrades);
        // Só a final é arredondada: (média projeto + média apresentação) / 2 sem
        // arredondar as parciais = média de todas as notas (mesmo número em cada lista).
        $final = Grade::average(array_merge($projectGrades, $presentationGrades));
        $passing = $this->settings->get()->getPassingGrade();
        $passed = Grade::isAtLeast($final, $passing);

        $defense->close($project, $presentation, $final, $passed ? DefenseOutcome::Passed : DefenseOutcome::Failed);

        if ($passed) {
            $tcc->recordFinalGrade($final);
            $this->apply($tcc, 'defense_pass', \sprintf('Defesa aprovada com média %s.', str_replace('.', ',', $final)));

            return;
        }

        $this->apply($tcc, 'defense_fail', \sprintf(
            'Média %s abaixo da mínima (%s). Aguardando decisão da coordenação.',
            str_replace('.', ',', $final),
            str_replace('.', ',', $passing),
        ));
    }

    private function currentDefense(Tcc $tcc): Defense
    {
        if (TccStatus::AwaitingDefense !== $tcc->getStatus()) {
            throw new TransitionNotAllowed('Este TCC não está aguardando defesa.');
        }

        return $tcc->getCurrentDefense() ?? throw new \LogicException('TCC aguardando defesa sem tentativa aberta.');
    }

    private function apply(Tcc $tcc, string $transition, string $comment): void
    {
        try {
            $this->workflow->apply($tcc, $transition, ['comment' => $comment]);
        } catch (NotEnabledTransitionException) {
            throw new TransitionNotAllowed(\sprintf('Esta ação não é permitida com o TCC em "%s".', $tcc->getStatus()->label()));
        }
    }
}
