<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdvisingMeeting;
use App\Entity\Tcc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdvisingMeeting>
 */
class AdvisingMeetingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdvisingMeeting::class);
    }

    /** @return list<AdvisingMeeting> Mais recente primeiro. */
    public function forTcc(Tcc $tcc): array
    {
        /* @var list<AdvisingMeeting> */
        return $this->createQueryBuilder('m')
            ->where('m.tcc = :tcc')
            ->setParameter('tcc', $tcc->getId(), 'uuid')
            ->orderBy('m.heldOn', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
