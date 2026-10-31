<?php

declare(strict_types=1);

namespace App\Dto;

use App\Exception\DomainRuleViolation;
use Symfony\Component\Validator\Constraints as Assert;

final class ScheduleDefenseRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe a data e a hora da defesa.')]
        public readonly string $scheduledAt = '',

        #[Assert\Length(max: 255, maxMessage: 'A sala pode ter no máximo {{ limit }} caracteres.')]
        public readonly ?string $location = null,

        #[Assert\Length(max: 500, maxMessage: 'O link pode ter no máximo {{ limit }} caracteres.')]
        public readonly ?string $meetingUrl = null,
    ) {
    }

    /**
     * Aceita o formato do campo datetime-local do navegador ("2026-11-20T14:00"),
     * interpretado no fuso da instituição, ou uma data ISO com fuso.
     */
    public function scheduledAt(string $timezone): \DateTimeImmutable
    {
        $value = trim($this->scheduledAt);
        $zone = new \DateTimeZone($timezone);
        $date = 1 === preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $zone)
            : (\DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $value)
                ?: \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value));

        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $date || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new DomainRuleViolation('Data e hora da defesa inválidas.');
        }

        return $date;
    }
}
