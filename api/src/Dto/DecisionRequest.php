<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class DecisionRequest
{
    public const NEW_DEFENSE = 'new_defense';
    public const REJECT = 'reject';

    public function __construct(
        #[Assert\Choice(choices: [self::NEW_DEFENSE, self::REJECT], message: 'Escolha permitir nova defesa ou reprovar.')]
        public readonly string $action = '',

        #[Assert\NotBlank(message: 'Explique a decisão no comentário.')]
        #[Assert\Length(max: 5000, maxMessage: 'O comentário pode ter no máximo {{ limit }} caracteres.')]
        public readonly string $comment = '',
    ) {
    }
}
