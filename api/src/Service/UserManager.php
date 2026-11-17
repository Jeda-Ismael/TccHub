<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\Role;
use App\Exception\DomainRuleViolation;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Casos de uso de contas: cadastro público, criação pela coordenação,
 * troca de papéis e exclusão.
 */
final class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /** Cadastro público: sempre como Aluno. */
    public function register(string $name, string $email, string $plainPassword): User
    {
        return $this->create($name, $email, $plainPassword, [Role::Student]);
    }

    /** @param list<Role> $roles */
    public function create(string $name, string $email, string $plainPassword, array $roles): User
    {
        $user = new User($name, $email, $roles);
        if (null !== $this->users->findOneByEmail($user->getEmail())) {
            throw new DomainRuleViolation('Já existe um usuário com este e-mail.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** @param list<Role> $roles */
    public function changeRoles(User $user, array $roles): void
    {
        $losesCoordination = $user->hasRole(Role::Coordination) && !\in_array(Role::Coordination, $roles, true);
        if ($losesCoordination) {
            $this->assertNotLastCoordination();
        }

        $user->changeRoles($roles);
        $this->em->flush();
    }

    public function delete(User $user, User $actingUser): void
    {
        if ($user->getId()->equals($actingUser->getId())) {
            throw new DomainRuleViolation('Você não pode excluir o seu próprio usuário.');
        }
        if ($user->hasRole(Role::Coordination)) {
            $this->assertNotLastCoordination();
        }

        try {
            $this->em->remove($user);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new DomainRuleViolation('Não é possível excluir: este usuário tem TCCs, avaliações ou documentos vinculados.');
        }
    }

    private function assertNotLastCoordination(): void
    {
        if ($this->users->countWithRole(Role::Coordination) <= 1) {
            throw new DomainRuleViolation('O sistema precisa ter pelo menos um usuário da Coordenação.');
        }
    }
}
