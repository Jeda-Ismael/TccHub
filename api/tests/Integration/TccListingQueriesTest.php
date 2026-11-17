<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dto\TccView;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TccStatus;
use App\Factory\KeywordFactory;
use App\Factory\ThematicAreaFactory;
use App\Factory\UserFactory;
use App\Repository\TccRepository;
use App\Tests\Support\QueryCounter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Zenstruck\Foundry\Test\Factories;

/**
 * A listagem não pode fazer consultas por TCC (N+1): a coordenação lista todos.
 */
final class TccListingQueriesTest extends KernelTestCase
{
    use Factories;

    private function createTccs(int $count): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $area = ThematicAreaFactory::createOne();
        $keyword = KeywordFactory::createOne();
        $evaluator = UserFactory::new()->evaluator()->create();
        for ($i = 0; $i < $count; ++$i) {
            $student = UserFactory::new()->student()->create();
            $tcc = new Tcc("TCC $i", 'Resumo', $student);
            $tcc->replaceThematicAreas([$area]);
            $tcc->replaceKeywords([$keyword]);
            $tcc->addDocument("tcc/$i.pdf", 'tcc.pdf', 10, str_repeat('a', 64), $student);
            $member = $tcc->addBoardMember($evaluator);
            $tcc->recordStatusChange(null, $tcc->getStatus(), 'create', $student);
            // Metade com defesa agendada e nota lançada, para cobrir defesas e notas
            if (0 === $i % 2) {
                $tcc->setMarking(TccStatus::AwaitingDefense->value);
                $defense = $tcc->openDefense();
                $defense->schedule(new \DateTimeImmutable('-1 hour'), 'Sala 1', null, $evaluator);
                $defense->grade($member, 8, 9, null);
            }
            $em->persist($tcc);
        }
        $em->flush();
        $em->clear();
    }

    /** Consultas para listar e montar a resposta de todos os TCCs, como no GET /api/tcc. */
    private function queriesToListAll(User $viewer): int
    {
        $container = self::getContainer();
        $container->get(EntityManagerInterface::class)->clear();
        $viewer = $container->get(EntityManagerInterface::class)->find(User::class, $viewer->getId()) ?? $viewer;
        $container->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($viewer, 'api', $viewer->getRoles()));
        $container->get(TccView::class)->reset();
        $counter = $container->get(QueryCounter::class);
        $counter->reset();

        foreach ($container->get(TccRepository::class)->findVisibleAs($viewer, Role::Coordination) as $tcc) {
            $container->get(TccView::class)->present($tcc, $viewer);
        }

        return $counter->count();
    }

    public function testNumberOfQueriesDoesNotGrowWithTheNumberOfTccs(): void
    {
        $coordination = UserFactory::new()->withRoles([Role::Coordination])->create();

        $this->createTccs(2);
        $withTwo = $this->queriesToListAll($coordination);
        $this->createTccs(8);
        $withTen = $this->queriesToListAll($coordination);

        self::assertSame($withTwo, $withTen, \sprintf('2 TCCs: %d consultas; 10 TCCs: %d consultas', $withTwo, $withTen));
        self::assertLessThanOrEqual(8, $withTen);
    }
}
