<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\PeriodType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;

/** Responde se há um prazo aberto agora (criar TCC exige submissão; parecer exige avaliação). */
final class PeriodChecker
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function isOpen(PeriodType $type): bool
    {
        $now = $this->clock->now()->format(\DATE_ATOM);

        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM submission_periods WHERE type = ? AND start_date <= ? AND end_date >= ? LIMIT 1',
            [$type->value, $now, $now],
        );
    }
}
