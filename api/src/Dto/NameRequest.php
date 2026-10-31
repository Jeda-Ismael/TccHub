<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Corpo de criação/edição de áreas temáticas e palavras-chave. */
final class NameRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o nome.')]
        #[Assert\Length(max: 120, maxMessage: 'O nome pode ter no máximo {{ limit }} caracteres.')]
        public readonly string $name = '',
    ) {
    }
}
