<?php

declare(strict_types=1);

namespace App\Util;

use App\Exception\DomainRuleViolation;

/**
 * Notas são guardadas como numeric(4,2) no Postgres e trafegam como string
 * no PHP (é assim que o Doctrine mapeia DECIMAL), evitando erro de ponto flutuante.
 */
final class Grade
{
    public const MIN = 0.0;
    public const MAX = 10.0;

    /** Valida e normaliza para duas casas decimais: 8.5 → "8.50". */
    public static function normalize(string|int|float $value, string $field = 'nota'): string
    {
        if (\is_string($value) && !is_numeric($value)) {
            throw new DomainRuleViolation(\sprintf('A %s deve ser um número.', $field));
        }

        $number = (float) $value;
        if ($number < self::MIN || $number > self::MAX) {
            throw new DomainRuleViolation(\sprintf('A %s deve estar entre 0 e 10.', $field));
        }

        return number_format($number, 2, '.', '');
    }
}
