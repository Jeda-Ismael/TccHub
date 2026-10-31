<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TccStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tcc>
 */
class TccRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tcc::class);
    }

    /**
     * TCCs que o usuário vê com um papel: os próprios (aluno), os orientados,
     * os das bancas de que participa, ou todos (coordenação).
     *
     * @return list<Tcc>
     */
    public function findVisibleAs(User $user, Role $as, ?TccStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('s', 'a')
            ->join('t.student', 's')
            ->leftJoin('t.advisor', 'a')
            ->orderBy('t.updatedAt', 'DESC');

        match ($as) {
            Role::Student => $qb->andWhere('t.student = :user'),
            Role::Advisor => $qb->andWhere('t.advisor = :user'),
            Role::Evaluator => $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\BoardMember bm WHERE bm.tcc = t AND bm.evaluator = :user)'),
            Role::Coordination => null,
        };
        if (Role::Coordination !== $as) {
            $qb->setParameter('user', $user->getId(), 'uuid');
        }
        if (null !== $status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        /* @var list<Tcc> */
        return $qb->getQuery()->getResult();
    }
}
