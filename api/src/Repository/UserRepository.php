<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\Role;
use App\Http\Pagination;
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

    /**
     * Usuários de um papel (ou todos), por nome. Com `$onlyActive`, deixa de
     * fora os desativados (ex.: ao escolher orientador ou banca).
     *
     * @return list<User>
     */
    public function findByRole(?Role $role, bool $onlyActive = false): array
    {
        return $this->search($role, null, $onlyActive, null)['items'];
    }

    /**
     * Busca da coordenação: por papel, por nome/e-mail e, com `$pagination`, por página.
     *
     * @return array{items: list<User>, total: int}
     */
    public function search(?Role $role, ?string $term, bool $onlyActive, ?Pagination $pagination): array
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.name', 'ASC')->addOrderBy('u.id', 'ASC');
        if (null !== $role) {
            // roles é jsonb: o operador @> verifica se o array contém o papel
            $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
                'SELECT id FROM users WHERE roles @> :role::jsonb',
                ['role' => json_encode([$role->value], \JSON_THROW_ON_ERROR)],
            );
            if ([] === $ids) {
                return ['items' => [], 'total' => 0];
            }
            $qb->andWhere('u.id IN (:ids)')->setParameter('ids', $ids);
        }
        if ($onlyActive) {
            $qb->andWhere('u.active = true');
        }
        $term = null === $term ? '' : trim($term);
        if ('' !== $term) {
            $qb->andWhere('(LOWER(u.name) LIKE :term OR u.email LIKE :term)')
                ->setParameter('term', '%'.addcslashes(mb_strtolower($term), '%_\\').'%');
        }

        if (null === $pagination) {
            /** @var list<User> $all */
            $all = $qb->getQuery()->getResult();

            return ['items' => $all, 'total' => \count($all)];
        }

        $total = (int) (clone $qb)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        /** @var list<User> $items */
        $items = $qb->setFirstResult($pagination->offset())->setMaxResults($pagination->perPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countWithRole(Role $role, bool $onlyActive = false): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM users WHERE roles @> :role::jsonb'.($onlyActive ? ' AND active' : ''),
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
