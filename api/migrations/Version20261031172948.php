<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reenvio exige PDF novo: guarda a versão do documento quando as correções foram pedidas.
 */
final class Version20261031172948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TCC: versão do PDF no pedido de correções';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tccs ADD corrections_requested_at_version INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tccs DROP corrections_requested_at_version');
    }
}
