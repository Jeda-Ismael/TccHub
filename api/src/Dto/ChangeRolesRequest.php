<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Role;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangeRolesRequest
{
    /**
     * @param list<string> $roles nomes da API: STUDENT, ADVISOR, EVALUATOR, COORDINATION
     */
    public function __construct(
        #[Assert\Count(min: 1, minMessage: 'Escolha pelo menos um papel.')]
        #[Assert\All([new Assert\Choice(callback: [Role::class, 'apiNames'], message: 'Papel inválido.')])]
        public readonly array $roles = [],
    ) {
    }

    /** @return list<Role> */
    public function roleList(): array
    {
        return array_map(Role::fromApiName(...), $this->roles);
    }
}
