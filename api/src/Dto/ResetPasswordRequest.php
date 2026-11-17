<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Link de troca de senha incompleto.')]
        public readonly string $token = '',

        #[Assert\NotBlank(message: 'Informe a nova senha.')]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'A senha precisa ter pelo menos {{ limit }} caracteres.')]
        public readonly string $password = '',
    ) {
    }
}
