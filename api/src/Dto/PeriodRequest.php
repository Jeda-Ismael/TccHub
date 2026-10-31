<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\PeriodType;
use App\Exception\DomainRuleViolation;
use Symfony\Component\Validator\Constraints as Assert;

final class PeriodRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Escolha o tipo do prazo.')]
        #[Assert\Choice(callback: [self::class, 'types'], message: 'Tipo de prazo inválido.')]
        public readonly string $type = '',

        #[Assert\NotBlank(message: 'Informe o nome do prazo.')]
        #[Assert\Length(max: 120, maxMessage: 'O nome pode ter no máximo {{ limit }} caracteres.')]
        public readonly string $label = '',

        #[Assert\NotBlank(message: 'Informe a data de início.')]
        public readonly string $startDate = '',

        #[Assert\NotBlank(message: 'Informe a data de término.')]
        public readonly string $endDate = '',
    ) {
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_map(static fn (PeriodType $type): string => $type->value, PeriodType::cases());
    }

    public function typeEnum(): PeriodType
    {
        return PeriodType::from($this->type);
    }

    /** Uma data sem hora (AAAA-MM-DD) vale desde 00:00 daquele dia, no fuso da instituição. */
    public function start(string $timezone): \DateTimeImmutable
    {
        return self::parse($this->startDate, '00:00:00', $timezone, 'Data de início inválida.');
    }

    /** Uma data sem hora (AAAA-MM-DD) vale até 23:59:59 daquele dia, no fuso da instituição. */
    public function end(string $timezone): \DateTimeImmutable
    {
        return self::parse($this->endDate, '23:59:59', $timezone, 'Data de término inválida.');
    }

    private static function parse(string $value, string $timeOfDay, string $timezone, string $error): \DateTimeImmutable
    {
        $zone = new \DateTimeZone($timezone);
        $date = 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value.' '.$timeOfDay, $zone)
            : (\DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $value)
                ?: \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value));

        if (false === $date) {
            throw new DomainRuleViolation($error);
        }

        return $date;
    }
}
