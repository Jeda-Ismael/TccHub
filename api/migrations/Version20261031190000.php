<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Busca do repositório (Fase 7): índice full-text dos TCCs publicados.
 *
 * - Configuração `pt_unaccent`: português (radicais: "fraude" acha "fraudes")
 *   e sem acento ("informacao" acha "informação").
 * - Tabela `tcc_search` fora das entidades (o Doctrine não conhece `tsvector`;
 *   ver schema_filter em doctrine.yaml). Só TCCs publicados têm linha aqui.
 */
final class Version20261031190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repositório: índice full-text (tcc_search) com português sem acento';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent');
        $this->addSql('CREATE TEXT SEARCH CONFIGURATION pt_unaccent (COPY = pg_catalog.portuguese)');
        $this->addSql('ALTER TEXT SEARCH CONFIGURATION pt_unaccent ALTER MAPPING FOR hword, hword_part, word WITH unaccent, portuguese_stem');
        $this->addSql('CREATE TABLE tcc_search (tcc_id UUID NOT NULL PRIMARY KEY REFERENCES tccs (id) ON DELETE CASCADE, document TSVECTOR NOT NULL)');
        $this->addSql('CREATE INDEX idx_tcc_search_document ON tcc_search USING GIN (document)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tcc_search');
        $this->addSql('DROP TEXT SEARCH CONFIGURATION pt_unaccent');
    }
}
