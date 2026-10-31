<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tcc;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Serializa as ações sobre um mesmo TCC: transação com a linha travada e tudo
 * o que outra requisição pode ter mudado recarregado do banco.
 *
 * Por que recarregar além do TCC: o refresh do TCC recarrega as coleções, mas
 * as entidades filhas já em memória (votos da banca, notas da defesa) mantêm os
 * dados antigos. Sem isso, um voto ou nota recém-gravado parecia pendente e a
 * rodada nunca fechava (bug encontrado na revisão do PR #4).
 */
final class TccLocker
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Roda a operação travada. Desfaz tudo se algo falhar, sem fechar o
     * EntityManager (o wrapInTransaction do Doctrine fecha, e uma regra de
     * negócio barrada é um caso normal, não um erro do banco).
     *
     * @param callable(): void $operation
     */
    public function run(Tcc $tcc, callable $operation): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->lockAndReload($tcc);
            $operation();
            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function lockAndReload(Tcc $tcc): void
    {
        $this->em->lock($tcc, LockMode::PESSIMISTIC_WRITE);
        $this->em->refresh($tcc);
        foreach ($tcc->getBoardMembers() as $member) {
            $this->em->refresh($member);
        }
        $defense = $tcc->getCurrentDefense();
        if (null !== $defense) {
            $this->em->refresh($defense);
            foreach ($defense->getGrades() as $grade) {
                $this->em->refresh($grade);
            }
        }
    }
}
