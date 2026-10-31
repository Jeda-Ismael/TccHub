<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BoardMember;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return list<Notification> As mais recentes primeiro. */
    public function findLatestFor(User $user, bool $onlyUnread, int $limit): array
    {
        $qb = $this->createQueryBuilder('n')
            ->addSelect('t')
            ->leftJoin('n.tcc', 't')
            ->where('n.recipient = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit);
        if ($onlyUnread) {
            $qb->andWhere('n.readAt IS NULL');
        }

        /* @var list<Notification> */
        return $qb->getQuery()->getResult();
    }

    /**
     * TCCs destes avisos em cuja banca o usuário está, numa consulta só.
     *
     * @param list<Notification> $notifications
     *
     * @return array<string, true> ids (RFC 4122) como chaves
     */
    public function tccIdsWhereOnBoard(User $user, array $notifications): array
    {
        $tccs = [];
        foreach ($notifications as $n) {
            if (null !== $tcc = $n->getTcc()) {
                $tccs[$tcc->getId()->toRfc4122()] = $tcc;
            }
        }
        if ([] === $tccs) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(bm.tcc) AS tcc')
            ->from(BoardMember::class, 'bm')
            ->where('bm.evaluator = :user')
            ->andWhere('bm.tcc IN (:tccs)')
            ->setParameter('user', $user->getId(), 'uuid')
            ->setParameter('tccs', array_values($tccs))
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $ids[Uuid::fromString((string) $row['tcc'])->toRfc4122()] = true;
        }

        return $ids;
    }

    public function countUnreadFor(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadFor(User $user, \DateTimeImmutable $at): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':at')
            ->where('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('at', $at, 'datetimetz_immutable')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
