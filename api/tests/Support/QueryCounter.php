<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Conta as consultas SQL feitas pelo Doctrine (registrado só no ambiente de
 * teste, via Doctrine\DBAL\Logging\Middleware em config/services.yaml).
 */
final class QueryCounter extends AbstractLogger
{
    private int $count = 0;

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if (isset($context['sql'])) {
            ++$this->count;
        }
    }

    public function reset(): void
    {
        $this->count = 0;
    }

    public function count(): int
    {
        return $this->count;
    }
}
