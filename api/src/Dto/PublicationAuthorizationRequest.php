<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PublicationAuthorizationRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Informe se autoriza a publicação.')]
        public readonly ?bool $authorized = null,
    ) {
    }
}
