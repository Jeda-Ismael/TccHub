<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Defense;
use App\Enum\DefenseOutcome;
use App\Enum\TccStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Defense>
 */
class DefenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Defense::class);
    }

    /**
     * Defesas que já começaram, ainda sem resultado e cuja banca ainda não foi
     * lembrada de lançar as notas.
     *
     * @return list<Defense>
     */
    public function findAwaitingGradesReminder(\DateTimeImmutable $now): array
    {
        /* @var list<Defense> */
        return $this->createQueryBuilder('d')
            ->join('d.tcc', 't')
            ->where('d.outcome = :pending')
            ->andWhere('d.scheduledAt <= :now')
            ->andWhere('d.gradesReminderSentAt IS NULL')
            ->andWhere('t.status = :awaiting')
            ->setParameter('pending', DefenseOutcome::Pending)
            ->setParameter('now', $now)
            ->setParameter('awaiting', TccStatus::AwaitingDefense->value)
            ->getQuery()
            ->getResult();
    }
}
