<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Settings;
use App\Enum\TccStatus;
use App\Repository\TccRepository;
use App\Repository\UserRepository;
use App\Search\RepositorySearch;
use App\Service\DocumentStorage;
use App\Story\BaseStory;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testBaseGroupHasUsersAndCatalogsButNoTccs(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('doctrine:fixtures:load'));
        $tester->execute(['--group' => ['base']], ['interactive' => false]);
        $tester->assertCommandIsSuccessful();

        self::assertSame(\count(BaseStory::USERS), self::getContainer()->get(UserRepository::class)->count([]));
        self::assertNotNull(self::getContainer()->get(EntityManagerInterface::class)->find(Settings::class, Settings::SINGLETON_ID));
        self::assertSame(0, self::getContainer()->get(TccRepository::class)->count([]));
    }

    public function testDemoDataFollowsTheRules(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('doctrine:fixtures:load'));
        $tester->execute(['--group' => ['demo']], ['interactive' => false]);
        $storage = self::getContainer()->get(DocumentStorage::class);

        foreach (self::getContainer()->get(TccRepository::class)->findAll() as $tcc) {
            // Histórico começa no rascunho e termina no status atual
            $history = $tcc->getStatusHistory()->getValues();
            self::assertNotEmpty($history);
            self::assertSame(TccStatus::Draft, $history[0]->getToStatus());
            self::assertSame($tcc->getStatus(), $history[\count($history) - 1]->getToStatus());
            // O orientador nunca está na banca
            self::assertNull(null === $tcc->getAdvisor() ? null : $tcc->findBoardMember($tcc->getAdvisor()));
            // O PDF existe no disco
            $document = $tcc->getCurrentDocument();
            self::assertNotNull($document);
            self::assertFileExists($storage->pathOf($document));
        }

        // Defesa agendada às 14h no horário da instituição
        $scheduled = array_values(array_filter(
            self::getContainer()->get(TccRepository::class)->findAll(),
            static fn ($t): bool => TccStatus::AwaitingDefense === $t->getStatus(),
        ))[0]->getCurrentDefense()?->getScheduledAt();
        self::assertSame('14:00', $scheduled?->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('H:i'));
    }
}
