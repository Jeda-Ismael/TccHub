<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Role;
use App\Exception\DomainRuleViolation;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    /** @var list<string> valores de {@see Role} */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $roles = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<Role> $roles
     */
    public function __construct(string $name, string $email, array $roles = [Role::Student])
    {
        $this->id = Uuid::v7();
        $this->rename($name);
        $this->changeEmail($email);
        $this->changeRoles($roles);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new DomainRuleViolation('O nome não pode ficar vazio.');
        }
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function changeEmail(string $email): void
    {
        $email = mb_strtolower(trim($email));
        if ('' === $email || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new DomainRuleViolation('E-mail inválido.');
        }
        $this->email = $email;
    }

    public function getUserIdentifier(): string
    {
        // changeEmail() nunca aceita vazio; o guarda cobre um registro corrompido no banco
        if ('' === $this->email) {
            throw new \LogicException('Usuário sem e-mail não pode ser autenticado.');
        }

        return $this->email;
    }

    /**
     * Papéis para o Symfony Security (inclui ROLE_USER, comum a todos os logados).
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return [...$this->roles, 'ROLE_USER'];
    }

    /** @return list<Role> */
    public function getRoleList(): array
    {
        return array_map(static fn (string $role): Role => Role::from($role), $this->roles);
    }

    public function hasRole(Role $role): bool
    {
        return \in_array($role->value, $this->roles, true);
    }

    /**
     * Regras: pelo menos um papel; Aluno não pode ser combinado com outro papel.
     * A regra "não remover o último coordenador" depende do banco e fica no serviço.
     *
     * @param list<Role> $roles
     */
    public function changeRoles(array $roles): void
    {
        $values = array_values(array_unique(array_map(static fn (Role $role): string => $role->value, $roles)));

        if ([] === $values) {
            throw new DomainRuleViolation('O usuário precisa ter pelo menos um papel.');
        }
        if (\in_array(Role::Student->value, $values, true) && \count($values) > 1) {
            throw new DomainRuleViolation('O papel Aluno não pode ser combinado com outros papéis.');
        }

        $order = array_map(static fn (Role $role): string => $role->value, Role::cases());
        usort($values, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

        $this->roles = $values;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /** Recebe a senha já criptografada (UserPasswordHasherInterface). */
    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
