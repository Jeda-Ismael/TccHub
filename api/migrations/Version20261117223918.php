<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reuniões de orientação (o orientador registra e o aluno confirma) e
 * cronograma do TCC (etapas com data limite, definidas pela coordenação).
 */
final class Version20261117223918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reuniões de orientação (advising_meetings) e cronograma (milestones)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE advising_meetings (id UUID NOT NULL, held_on DATE NOT NULL, summary TEXT NOT NULL, next_steps TEXT DEFAULT NULL, confirmed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, tcc_id UUID NOT NULL, advisor_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_advising_meetings_tcc ON advising_meetings (tcc_id, held_on)');
        $this->addSql('CREATE INDEX IDX_D99A350F66D3AD77 ON advising_meetings (advisor_id)');
        $this->addSql('ALTER TABLE advising_meetings ADD CONSTRAINT FK_D99A350F4F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE advising_meetings ADD CONSTRAINT FK_D99A350F66D3AD77 FOREIGN KEY (advisor_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('CREATE TABLE milestones (id UUID NOT NULL, name VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, due_date DATE NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE advising_meetings DROP CONSTRAINT FK_D99A350F4F70A726');
        $this->addSql('ALTER TABLE advising_meetings DROP CONSTRAINT FK_D99A350F66D3AD77');
        $this->addSql('DROP TABLE advising_meetings');
        $this->addSql('DROP TABLE milestones');
    }
}
