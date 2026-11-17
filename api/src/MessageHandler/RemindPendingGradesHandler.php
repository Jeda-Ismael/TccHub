<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\BoardMember;
use App\Enum\NotificationType;
use App\Message\RemindPendingGrades;
use App\Notification\Notifier;
use App\Repository\DefenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Lembrete GRADES_PENDING: quando o horário da defesa chega, avisa (sininho e
 * e-mail) quem da banca ainda não lançou as notas. Uma vez por defesa; se ela
 * for reagendada, o lembrete vale de novo.
 */
#[AsMessageHandler]
final class RemindPendingGradesHandler
{
    public function __construct(
        private readonly DefenseRepository $defenses,
        private readonly Notifier $notifier,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(RemindPendingGrades $message): int
    {
        $now = $this->clock->now();
        $reminded = 0;
        foreach ($this->defenses->findAwaitingGradesReminder($now) as $defense) {
            $tcc = $defense->getTcc();
            $missing = array_values(array_filter(
                $tcc->getBoardMembers()->toArray(),
                static fn (BoardMember $member): bool => null === $defense->findGradeBy($member),
            ));
            if ([] !== $missing) {
                $this->notifier->notify(
                    NotificationType::GradesPending,
                    $tcc,
                    array_map(static fn (BoardMember $member) => $member->getEvaluator(), $missing),
                    'Lance as notas da defesa',
                    \sprintf('A defesa de "%s", de %s, já começou. Lance as notas de projeto e de apresentação.', $tcc->getTitle(), $tcc->getStudent()->getName()),
                    skipActor: false,
                );
                ++$reminded;
            }
            $defense->markGradesReminderSent($now);
        }
        $this->em->flush();

        return $reminded;
    }
}
