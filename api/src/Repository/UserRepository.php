<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /** @return list<User> */
    public function findByRole(?Role $role): array
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.name', 'ASC');
        if (null !== $role) {
            // roles é jsonb: o operador @> verifica se o array contém o papel
            $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
                'SELECT id FROM users WHERE roles @> :role::jsonb',
                ['role' => json_encode([$role->value], \JSON_THROW_ON_ERROR)],
            );
            if ([] === $ids) {
                return [];
            }
            $qb->where('u.id IN (:ids)')->setParameter('ids', $ids);
        }

        /* @var list<User> */
        return $qb->getQuery()->getResult();
    }

    public function countWithRole(Role $role): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM users WHERE roles @> :role::jsonb',
            ['role' => json_encode([$role->value], \JSON_THROW_ON_ERROR)],
        );
    }

    /** Re-hash automático da senha quando o algoritmo fica mais forte. */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
