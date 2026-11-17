<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Notification;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\DefenseOutcome;
use App\Enum\EvaluationVerdict;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Enum\TccStatus;
use App\Factory\KeywordFactory;
use App\Factory\ThematicAreaFactory;
use App\Factory\UserFactory;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class PersistenceTest extends KernelTestCase
{
    use Factories;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    /** Monta um TCC com todas as relações, do jeito que o fluxo completo deixaria. */
    private function persistFullTcc(): Tcc
    {
        $student = UserFactory::new()->student()->create();
        $advisor = UserFactory::new()->advisor()->create();
        $evaluator = UserFactory::new()->evaluator()->create();
        $coordination = UserFactory::new()->coordination()->create();

        $tcc = new Tcc('Detecção de fraudes com aprendizado de máquina', 'Resumo do trabalho', $student);
        $tcc->changeAdvisor($advisor);
        $tcc->replaceThematicAreas([ThematicAreaFactory::createOne(['name' => 'IA'])]);
        $tcc->replaceKeywords([KeywordFactory::createOne(['name' => 'fraude'])]);
        $tcc->addDocument('tcc/v1.pdf', 'tcc-v1.pdf', 1024, str_repeat('a', 64), $student);
        $tcc->addDocument('tcc/v2.pdf', 'tcc-v2.pdf', 2048, str_repeat('b', 64), $student);
        $member = $tcc->addBoardMember($evaluator);
        $member->giveVerdict(EvaluationVerdict::Approved, 'Ótimo');
        $tcc->recordStatusChange(null, TccStatus::Draft, 'create', $student);
        $tcc->recordStatusChange(TccStatus::Draft, TccStatus::Submitted, 'submit', $coordination, 'Enviado');
        $tcc->setStatus(TccStatus::AwaitingDefense);
        $defense = $tcc->openDefense();
        $defense->schedule(new \DateTimeImmutable('-1 hour'), 'Sala 101', null, $advisor);
        $defense->grade($member, 8, '9.5', null);
        $defense->close('8.00', '9.50', '8.75', DefenseOutcome::Passed);
        $tcc->recordFinalGrade('8.75');

        $this->em->persist($tcc);
        $this->em->persist(new Notification($student, NotificationType::TccApproved, 'Aprovado', 'Parabéns', $tcc));
        $this->em->flush();
        $this->em->clear();

        return $tcc;
    }

    public function testPersistsAndReloadsTheWholeGraph(): void
    {
        $id = $this->persistFullTcc()->getId();

        $tcc = $this->em->find(Tcc::class, $id);

        self::assertNotNull($tcc);
        self::assertSame(TccStatus::AwaitingDefense, $tcc->getStatus());
        self::assertSame('8.75', $tcc->getFinalGrade());
        self::assertSame(['IA'], $tcc->getThematicAreas()->map(static fn ($a) => $a->getName())->toArray());
        self::assertSame(['fraude'], $tcc->getKeywords()->map(static fn ($k) => $k->getName())->toArray());
        self::assertSame(2, $tcc->getCurrentDocument()?->getVersion());
        self::assertSame(2048, $tcc->getCurrentDocument()->getSizeBytes());
        $member = $tcc->getBoardMembers()->first();
        self::assertNotFalse($member);
        self::assertSame(EvaluationVerdict::Approved, $member->getVerdict());
        self::assertSame(['create', 'submit'], $tcc->getStatusHistory()->map(static fn ($h) => $h->getTransition())->getValues());

        $defense = $tcc->getCurrentDefense();
        self::assertNotNull($defense);
        self::assertSame(DefenseOutcome::Passed, $defense->getOutcome());
        $grade = $defense->getGrades()->first();
        self::assertNotFalse($grade);
        self::assertSame('9.50', $grade->getPresentationGrade());
        self::assertSame('Sala 101', $defense->getLocation());
    }

    public function testRolesRoundTripThroughJsonb(): void
    {
        $id = UserFactory::new()->withRoles([Role::Coordination, Role::Advisor])->create()->getId();
        $this->em->clear();

        $user = $this->em->find(User::class, $id);

        self::assertSame([Role::Advisor, Role::Coordination], $user?->getRoleList());
    }

    public function testEmailIsUnique(): void
    {
        UserFactory::createOne(['email' => 'repetido@hubtcc.dev']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->persist(new User('Outro', 'REPETIDO@hubtcc.dev'));
        $this->em->flush();
    }

    public function testDeletingATccRemovesEverythingThatBelongsToIt(): void
    {
        $id = $this->persistFullTcc()->getId();
        $connection = $this->em->getConnection();

        $connection->executeStatement('DELETE FROM tccs WHERE id = ?', [$id->toRfc4122()]);

        foreach (['tcc_documents', 'board_members', 'defenses', 'tcc_status_history', 'tcc_thematic_areas', 'tcc_keywords', 'notifications'] as $table) {
            self::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM $table"), $table);
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM defense_grades'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM thematic_areas'), 'O catálogo não é apagado junto');
    }

    public function testUserWithTccCannotBeDeleted(): void
    {
        $tcc = $this->persistFullTcc();

        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->em->getConnection()->executeStatement('DELETE FROM users WHERE id = ?', [$tcc->getStudent()->getId()->toRfc4122()]);
    }

    public function testHistoryKeepsEntriesWhenTheAuthorIsDeleted(): void
    {
        $tcc = $this->persistFullTcc();
        $connection = $this->em->getConnection();
        $coordinationId = $connection->fetchOne("SELECT changed_by_id FROM tcc_status_history WHERE transition = 'submit'");

        $connection->executeStatement('DELETE FROM users WHERE id = ?', [$coordinationId]);

        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM tcc_status_history WHERE tcc_id = ?', [$tcc->getId()->toRfc4122()]));
        self::assertNull($connection->fetchOne("SELECT changed_by_id FROM tcc_status_history WHERE transition = 'submit'"));
    }

    public function testDatabaseRejectsGradesOutOfRange(): void
    {
        $this->persistFullTcc();

        $this->assertViolatesCheckConstraint('UPDATE defense_grades SET project_grade = 10.5');
    }

    public function testDatabaseAllowsOnlyOneSettingsRow(): void
    {
        $this->assertViolatesCheckConstraint('INSERT INTO settings (id, board_min_size, board_max_size, passing_grade) VALUES (2, 2, 3, 6)');
    }

    public function testDocumentVersionIsUniquePerTcc(): void
    {
        $this->persistFullTcc();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->getConnection()->executeStatement(
            "INSERT INTO tcc_documents (id, version, storage_path, original_name, size_bytes, sha256, uploaded_at, tcc_id, uploaded_by_id)
             SELECT gen_random_uuid(), 1, 'x', 'x', 1, repeat('c', 64), now(), tcc_id, uploaded_by_id FROM tcc_documents WHERE version = 1",
        );
    }

    public function testUpdatedAtChangesOnUpdate(): void
    {
        $user = UserFactory::createOne();
        $before = $user->getUpdatedAt();
        sleep(1);

        $user->rename('Novo Nome');
        $this->em->flush();

        self::assertGreaterThan($before, $user->getUpdatedAt());
    }

    private function assertViolatesCheckConstraint(string $sql): void
    {
        try {
            $this->em->getConnection()->executeStatement($sql);
            self::fail('O banco deveria ter rejeitado: '.$sql);
        } catch (DriverException $e) {
            self::assertSame('23514', $e->getSQLState(), 'Esperava violação de CHECK (SQLSTATE 23514)');
        }
    }
}
