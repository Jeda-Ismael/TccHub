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

    /**
     * Média com arredondamento meio-para-cima em 2 casas, calculada em
     * centésimos inteiros (sem erro de ponto flutuante): ["8.00", "9.50"] → "8.75".
     *
     * @param non-empty-list<string> $grades notas já normalizadas
     */
    public static function average(array $grades): string
    {
        $cents = array_map(self::toCents(...), $grades);

        return self::fromCents(intdiv(2 * array_sum($cents) + \count($cents), 2 * \count($cents)));
    }

    public static function isAtLeast(string $grade, string $minimum): bool
    {
        return self::toCents($grade) >= self::toCents($minimum);
    }

    private static function toCents(string $grade): int
    {
        [$units, $decimals] = array_pad(explode('.', $grade, 2), 2, '0');

        return (int) $units * 100 + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }

    private static function fromCents(int $cents): string
    {
        return \sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
