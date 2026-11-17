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

    /** O aluno já tem um TCC em andamento (nem aprovado nem reprovado)? */
    public function hasActiveFor(User $student): bool
    {
        return (bool) $this->createQueryBuilder('t')
            ->select('1')
            ->where('t.student = :student')
            ->andWhere('t.status NOT IN (:finished)')
            ->setParameter('student', $student->getId(), 'uuid')
            ->setParameter('finished', [TccStatus::Approved->value, TccStatus::Rejected->value])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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

        /** @var list<Tcc> $tccs */
        $tccs = $qb->getQuery()->getResult();
        $this->preloadCollections($tccs);

        return $tccs;
    }

    /**
     * Carrega as coleções que a resposta usa com uma consulta por coleção para
     * a lista inteira, em vez de várias consultas por TCC (N+1). Os TCCs já
     * estão no identity map; cada consulta só preenche uma coleção.
     *
     * @param list<Tcc> $tccs
     */
    private function preloadCollections(array $tccs): void
    {
        if ([] === $tccs) {
            return;
        }

        $joins = [
            't.thematicAreas' => 'ta',
            't.keywords' => 'kw',
            't.documents' => 'doc',
            't.boardMembers' => 'bm',
            't.statusHistory' => 'sh',
        ];
        foreach ($joins as $collection => $alias) {
            $qb = $this->createQueryBuilder('t')
                ->select('t', $alias)
                ->leftJoin($collection, $alias)
                ->where('t IN (:tccs)')
                ->setParameter('tccs', $tccs);
            // Quem avaliou e quem mudou o status também aparecem na resposta
            if ('bm' === $alias) {
                $qb->addSelect('ev')->leftJoin('bm.evaluator', 'ev');
            }
            if ('sh' === $alias) {
                $qb->addSelect('cb')->leftJoin('sh.changedBy', 'cb');
            }
            $qb->getQuery()->getResult();
        }
    }
}
