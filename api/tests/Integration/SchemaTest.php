<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garante que as migrations e o mapeamento das entidades contam a mesma história.
 */
final class SchemaTest extends KernelTestCase
{
    public function testMappingIsValidAndMigrationsMatchTheEntities(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $validator = new SchemaValidator($em);

        self::assertSame([], $validator->validateMapping());
        // A tabela de controle das migrations não é uma entidade: fica de fora da comparação
        $pending = array_values(array_filter(
            $validator->getUpdateSchemaList(),
            static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions'),
        ));

        self::assertSame([], $pending, 'Rode `make migration`: o banco está diferente das entidades.');
    }

    public function testMigrationCreatesDefaultSettings(): void
    {
        $settings = self::getContainer()->get(SettingsRepository::class)->get();

        self::assertSame(Settings::SINGLETON_ID, $settings->getId());
        self::assertSame(2, $settings->getBoardMinSize());
        self::assertSame(3, $settings->getBoardMaxSize());
        self::assertSame('6.00', $settings->getPassingGrade());
    }

    public function testSettingsRepositoryFailsLoudlyWithoutTheRow(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('DELETE FROM settings');

        $this->expectException(\LogicException::class);

        self::getContainer()->get(SettingsRepository::class)->get();
    }
}
