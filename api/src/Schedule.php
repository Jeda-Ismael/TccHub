<?php

declare(strict_types=1);

namespace App;

use App\Message\RemindPendingGrades;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tarefas periódicas, rodadas pelo serviço worker
 * (`messenger:consume async scheduler_default`).
 */
#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            // Se o worker ficou parado, roda uma vez ao voltar (não uma por execução perdida)
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            // Lembra a banca de lançar as notas depois que a defesa começou
            ->add(RecurringMessage::every('15 minutes', new RemindPendingGrades()));
    }
}
