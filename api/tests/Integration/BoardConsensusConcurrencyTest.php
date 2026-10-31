<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\EvaluationVerdict;
use App\Enum\TccStatus;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\UserFactory;
use App\Service\TccService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Regressão: dois avaliadores votando ao mesmo tempo. A requisição do segundo
 * já tinha a banca em memória (o Voter carrega para checar a permissão) quando
 * o primeiro gravou o voto. Antes da correção, o segundo não enxergava esse
 * voto, a rodada não fechava e o TCC ficava preso em SENT_TO_EVALUATION.
 */
final class BoardConsensusConcurrencyTest extends KernelTestCase
{
    use Factories;

    private EntityManagerInterface $em;

    /** @var list<User> */
    private array $evaluators;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        SubmissionPeriodFactory::new()->evaluation()->create();
        $this->evaluators = UserFactory::new()->evaluator()->many(3)->create();
    }

    /** TCC em avaliação com a banca informada, recarregado do zero como numa requisição nova. */
    private function tccInEvaluation(int $boardSize): Tcc
    {
        $tcc = new Tcc('Título', 'Resumo', UserFactory::new()->student()->create());
        $tcc->changeAdvisor(UserFactory::new()->advisor()->create());
        foreach (\array_slice($this->evaluators, 0, $boardSize) as $evaluator) {
            $tcc->addBoardMember($evaluator);
        }
        $tcc->setStatus(TccStatus::SentToEvaluation);
        $this->em->persist($tcc);
        $this->em->flush();
        $id = $tcc->getId();
        $this->em->clear();

        $fresh = $this->em->find(Tcc::class, $id);
        self::assertNotNull($fresh);
        // Como o TccVoter faz no início da requisição
        $fresh->getBoardMembers()->toArray();

        return $fresh;
    }

    /** Outra requisição gravou um voto (commit já feito) enquanto esta segurava a banca em memória. */
    private function voteFromAnotherRequest(User $evaluator, EvaluationVerdict $verdict): void
    {
        self::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE board_members SET verdict = ? WHERE evaluator_id = ?',
            [$verdict->value, $evaluator->getId()->toRfc4122()],
        );
    }

    private function statusOf(Uuid $id): TccStatus
    {
        $this->em->clear();

        return $this->em->find(Tcc::class, $id)?->getStatus() ?? throw new \LogicException('TCC sumiu');
    }

    public function testSecondVoterSeesTheFirstVoteAndClosesTheRound(): void
    {
        $tcc = $this->tccInEvaluation(2);
        [$first, $second] = $this->evaluators;
        $this->voteFromAnotherRequest($first, EvaluationVerdict::Approved);

        self::getContainer()->get(TccService::class)->evaluate($tcc, $this->em->find(User::class, $second->getId()) ?? $second, EvaluationVerdict::Approved, null);

        self::assertSame(TccStatus::AdvisorFinalReview, $this->statusOf($tcc->getId()));
    }

    public function testCorrectionFromTheOtherVoterIsNotLost(): void
    {
        $tcc = $this->tccInEvaluation(2);
        [$first, $second] = $this->evaluators;
        $this->voteFromAnotherRequest($first, EvaluationVerdict::CorrectionsRequested);

        self::getContainer()->get(TccService::class)->evaluate($tcc, $this->em->find(User::class, $second->getId()) ?? $second, EvaluationVerdict::Approved, null);

        self::assertSame(TccStatus::CorrectionsRequested, $this->statusOf($tcc->getId()));
    }

    public function testChangingTheBoardSeesVotesCastMeanwhile(): void
    {
        $tcc = $this->tccInEvaluation(3);
        [$first, $second] = $this->evaluators;
        $this->voteFromAnotherRequest($first, EvaluationVerdict::Approved);
        $this->voteFromAnotherRequest($second, EvaluationVerdict::Approved);

        // A coordenação tira o terceiro, que não votou: os dois votos que restam fecham a rodada
        self::getContainer()->get(TccService::class)->assignBoard($tcc, [$first->getId()->toRfc4122(), $second->getId()->toRfc4122()]);

        self::assertSame(TccStatus::AdvisorFinalReview, $this->statusOf($tcc->getId()));
    }
}
