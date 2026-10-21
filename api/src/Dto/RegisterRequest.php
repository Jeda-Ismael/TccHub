<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o nome.')]
        #[Assert\Length(max: 255, maxMessage: 'O nome pode ter no máximo {{ limit }} caracteres.')]
        public readonly string $name = '',

        #[Assert\NotBlank(message: 'Informe o e-mail.')]
        #[Assert\Email(message: 'E-mail inválido.')]
        #[Assert\Length(max: 180, maxMessage: 'O e-mail pode ter no máximo {{ limit }} caracteres.')]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Informe a senha.')]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'A senha precisa ter pelo menos {{ limit }} caracteres.')]
        public readonly string $password = '',
    ) {
    }
}
