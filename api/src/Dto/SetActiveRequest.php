<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SetActiveRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Informe se o usuário fica ativo (true) ou desativado (false).')]
        public readonly ?bool $active = null,
    ) {
    }
}
