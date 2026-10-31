<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class BoardRequest
{
    /** @param list<string> $evaluatorIds */
    public function __construct(
        #[Assert\All([new Assert\Type('string')])]
        public readonly array $evaluatorIds = [],
    ) {
    }
}
