<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ForgotPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o e-mail.')]
        #[Assert\Email(message: 'E-mail inválido.')]
        public readonly string $email = '',
    ) {
    }
}
