<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\TccStatus;
use App\Repository\TccRepository;
use App\Search\RepositorySearch;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `make demo`: os dados de demonstração carregam e o repositório já tem o que mostrar.
 */
final class DemoFixturesTest extends KernelTestCase
{
    public function testDemoLoadsTccsInSeveralStatusesAndPublishesSome(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('doctrine:fixtures:load'));
        $tester->execute(['--group' => ['demo']], ['interactive' => false]);
        $tester->assertCommandIsSuccessful();

        $statuses = array_map(static fn ($t) => $t->getStatus(), self::getContainer()->get(TccRepository::class)->findAll());
        foreach ([TccStatus::Approved, TccStatus::SentToEvaluation, TccStatus::Submitted, TccStatus::AwaitingDefense] as $status) {
            self::assertContains($status, $statuses);
        }

        $search = self::getContainer()->get(RepositorySearch::class);
        self::assertSame(4, $search->search(null, null, null, null, 1)['total']);
        self::assertSame('Detecção de fraudes em cartões com aprendizado de máquina', $search->search('fraude', null, null, null, 1)['items'][0]->getTitle());
    }

    public function testBaseGroupHasNoTccs(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('doctrine:fixtures:load'));
        $tester->execute(['--group' => ['base']], ['interactive' => false]);

        self::assertSame(0, self::getContainer()->get(TccRepository::class)->count([]));
    }
}
