<?php

declare(strict_types=1);

namespace App\Dto;

use App\Exception\DomainRuleViolation;
use Symfony\Component\Validator\Constraints as Assert;

final class MeetingRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe a data da reunião.')]
        public readonly string $heldOn = '',

        #[Assert\NotBlank(message: 'Escreva o que foi conversado na reunião.')]
        #[Assert\Length(max: 5000, maxMessage: 'O resumo pode ter no máximo {{ limit }} caracteres.')]
        public readonly string $summary = '',

        #[Assert\Length(max: 2000, maxMessage: 'Os próximos passos podem ter no máximo {{ limit }} caracteres.')]
        public readonly ?string $nextSteps = null,
    ) {
    }

    /** Data no formato AAAA-MM-DD, no fuso da instituição. */
    public function date(string $timezone): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->heldOn, new \DateTimeZone($timezone));
        if (false === $date || $date->format('Y-m-d') !== $this->heldOn) {
            throw new DomainRuleViolation('Data da reunião inválida.');
        }

        return $date;
    }
}
