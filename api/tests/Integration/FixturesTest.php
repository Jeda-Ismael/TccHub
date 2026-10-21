<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\PeriodType;
use App\Enum\Role;
use App\Factory\UserFactory;
use App\Repository\KeywordRepository;
use App\Repository\SubmissionPeriodRepository;
use App\Repository\ThematicAreaRepository;
use App\Repository\UserRepository;
use App\Story\BaseStory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

final class FixturesTest extends KernelTestCase
{
    use Factories;

    public function testBaseStoryCreatesOneUserPerRoleWithKnownPassword(): void
    {
        BaseStory::load();

        $users = self::getContainer()->get(UserRepository::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        self::assertSame(\count(BaseStory::USERS), $users->count([]));

        $professor = $users->findOneByEmail(' PROFESSOR@hubtcc.dev ');
        self::assertNotNull($professor);
        self::assertSame([Role::Advisor, Role::Evaluator, Role::Coordination], $professor->getRoleList());
        self::assertTrue($hasher->isPasswordValid($professor, UserFactory::DEFAULT_PASSWORD));

        self::assertSame([Role::Student], $users->findOneByEmail('aluno@hubtcc.dev')?->getRoleList());
    }

    public function testBaseStoryOpensSubmissionAndEvaluationPeriods(): void
    {
        BaseStory::load();
        $now = new \DateTimeImmutable();

        $periods = self::getContainer()->get(SubmissionPeriodRepository::class)->findAll();
        $active = array_map(static fn ($p) => $p->getType(), array_filter($periods, static fn ($p) => $p->isActiveAt($now)));

        self::assertEqualsCanonicalizing([PeriodType::Submission, PeriodType::Evaluation], array_values($active));
        self::assertSame(\count(BaseStory::THEMATIC_AREAS), self::getContainer()->get(ThematicAreaRepository::class)->count([]));
        self::assertSame(\count(BaseStory::KEYWORDS), self::getContainer()->get(KeywordRepository::class)->count([]));
    }
}
