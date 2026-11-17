<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Usuários podem ser desativados em vez de excluídos (quem tem TCC, parecer
 * ou documento não pode ser excluído sem perder o histórico).
 */
final class Version20261117000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Usuários: coluna active (desativar em vez de excluir)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD active BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP active');
    }
}
