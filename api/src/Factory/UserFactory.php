<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const DEFAULT_PASSWORD = '123456';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    public function student(): self
    {
        return $this->with(['roles' => [Role::Student]]);
    }

    public function advisor(): self
    {
        return $this->with(['roles' => [Role::Advisor]]);
    }

    public function evaluator(): self
    {
        return $this->with(['roles' => [Role::Evaluator]]);
    }

    public function coordination(): self
    {
        return $this->with(['roles' => [Role::Coordination]]);
    }

    /** @param list<Role> $roles */
    public function withRoles(array $roles): self
    {
        return $this->with(['roles' => $roles]);
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->name(),
            'email' => self::faker()->unique()->safeEmail(),
            'roles' => [Role::Student],
            'plainPassword' => self::DEFAULT_PASSWORD,
        ];
    }

    protected function initialize(): static
    {
        return $this
            ->instantiateWith(Instantiator::withConstructor()->allowExtra('plainPassword'))
            ->afterInstantiate(function (User $user, array $attributes): void {
                $user->setPassword($this->passwordHasher->hashPassword($user, (string) $attributes['plainPassword']));
            });
    }
}
