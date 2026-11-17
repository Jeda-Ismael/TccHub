<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SettingsRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'O mínimo da banca deve estar entre {{ min }} e {{ max }}.')]
        public readonly int $boardMinSize = 0,

        #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'O máximo da banca deve estar entre {{ min }} e {{ max }}.')]
        public readonly int $boardMaxSize = 0,

        #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'A nota mínima deve estar entre {{ min }} e {{ max }}.')]
        public readonly float $passingGrade = -1,
    ) {
    }
}
