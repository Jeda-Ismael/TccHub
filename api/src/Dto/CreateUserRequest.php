<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Role;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateUserRequest
{
    /**
     * @param list<string> $roles nomes da API: STUDENT, ADVISOR, EVALUATOR, COORDINATION
     */
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
