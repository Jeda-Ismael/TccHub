<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findOneByPlainToken(string $plainToken): ?PasswordResetToken
    {
        return $this->findOneBy(['tokenHash' => PasswordResetToken::hash($plainToken)]);
    }

    /** Apaga todos os pedidos do usuário (um link novo invalida os antigos). */
    public function deleteAllFor(User $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
