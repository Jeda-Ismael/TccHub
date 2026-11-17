<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TccStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TccStatusHistory>
 */
class TccStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TccStatusHistory::class);
    }
}
