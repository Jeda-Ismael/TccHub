<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdvisorReviewRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Informe se aprova ou não.')]
        public readonly ?bool $approve = null,

        #[Assert\Length(max: 5000, maxMessage: 'O comentário pode ter no máximo {{ limit }} caracteres.')]
        public readonly ?string $comment = null,
    ) {
    }
}
