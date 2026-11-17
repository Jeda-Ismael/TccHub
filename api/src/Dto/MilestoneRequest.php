<?php

declare(strict_types=1);

namespace App\Dto;

use App\Exception\DomainRuleViolation;
use Symfony\Component\Validator\Constraints as Assert;

final class MilestoneRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o nome da etapa.')]
        #[Assert\Length(max: 120, maxMessage: 'O nome pode ter no máximo {{ limit }} caracteres.')]
        public readonly string $name = '',

        #[Assert\Length(max: 1000, maxMessage: 'A descrição pode ter no máximo {{ limit }} caracteres.')]
        public readonly ?string $description = null,

        #[Assert\NotBlank(message: 'Informe a data limite.')]
        public readonly string $dueDate = '',
    ) {
    }

    /** Data no formato AAAA-MM-DD. */
    public function date(string $timezone): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->dueDate, new \DateTimeZone($timezone));
        if (false === $date || $date->format('Y-m-d') !== $this->dueDate) {
            throw new DomainRuleViolation('Data limite inválida.');
        }

        return $date;
    }
}
