<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Enum\Role;

/**
 * Formato de um usuário nas respostas da API.
 */
final class UserView
{
    /**
     * @return array{id: string, name: string, email: string, roles: list<string>, active: bool, createdAt: string}
     */
    public static function from(User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'roles' => array_map(static fn (Role $role): string => $role->apiName(), $user->getRoleList()),
            'active' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * Versão curta, para listas e para quem não é coordenação.
     *
     * @return array{id: string, name: string, email: string}
     */
    public static function summary(User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        ];
    }
}
