<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Esquema inicial do hubtcc (Fase 1). Ver docs/ARQUITETURA.md §6.
 */
final class Version20261021003349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Esquema inicial: usuários, TCCs, documentos, banca, defesas, notas, histórico, notificações e configurações';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE board_members (id UUID NOT NULL, verdict VARCHAR(30) NOT NULL, comment TEXT DEFAULT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, tcc_id UUID NOT NULL, evaluator_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_board_members_tcc_evaluator ON board_members (tcc_id, evaluator_id)');
        $this->addSql('CREATE INDEX IDX_DBEFAF04F70A726 ON board_members (tcc_id)');
        $this->addSql('CREATE INDEX IDX_DBEFAF043575BE2 ON board_members (evaluator_id)');
        $this->addSql('CREATE TABLE defense_grades (id UUID NOT NULL, project_grade NUMERIC(4, 2) NOT NULL, presentation_grade NUMERIC(4, 2) NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, defense_id UUID NOT NULL, board_member_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_defense_grades_member ON defense_grades (defense_id, board_member_id)');
        $this->addSql('CREATE INDEX IDX_714DDFCBFB0C2DCF ON defense_grades (defense_id)');
        $this->addSql('CREATE INDEX IDX_714DDFCBC7BA2FD5 ON defense_grades (board_member_id)');
        $this->addSql('CREATE TABLE defenses (id UUID NOT NULL, attempt INT NOT NULL, scheduled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, meeting_url VARCHAR(500) DEFAULT NULL, project_average NUMERIC(4, 2) DEFAULT NULL, presentation_average NUMERIC(4, 2) DEFAULT NULL, final_grade NUMERIC(4, 2) DEFAULT NULL, outcome VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, tcc_id UUID NOT NULL, scheduled_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_defenses_tcc_attempt ON defenses (tcc_id, attempt)');
        $this->addSql('CREATE INDEX IDX_3BBAEF4D4F70A726 ON defenses (tcc_id)');
        $this->addSql('CREATE INDEX IDX_3BBAEF4DE89A792F ON defenses (scheduled_by_id)');
        $this->addSql('CREATE TABLE keywords (id UUID NOT NULL, name VARCHAR(120) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AA5FB55E5E237E06 ON keywords (name)');
        $this->addSql('CREATE TABLE notifications (id UUID NOT NULL, type VARCHAR(40) NOT NULL, title VARCHAR(255) NOT NULL, body TEXT NOT NULL, read_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, tcc_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_notifications_unread ON notifications (user_id, read_at)');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D34F70A726 ON notifications (tcc_id)');
        $this->addSql('CREATE TABLE settings (id INT NOT NULL, board_min_size INT NOT NULL, board_max_size INT NOT NULL, passing_grade NUMERIC(4, 2) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE submission_periods (id UUID NOT NULL, type VARCHAR(20) NOT NULL, label VARCHAR(120) NOT NULL, start_date TIMESTAMP(0) WITH TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_submission_periods_active ON submission_periods (type, start_date, end_date)');
        $this->addSql('CREATE TABLE tcc_documents (id UUID NOT NULL, version INT NOT NULL, storage_path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, size_bytes BIGINT NOT NULL, sha256 CHAR(64) NOT NULL, uploaded_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, tcc_id UUID NOT NULL, uploaded_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tcc_documents_version ON tcc_documents (tcc_id, version)');
        $this->addSql('CREATE INDEX IDX_EF65A5B24F70A726 ON tcc_documents (tcc_id)');
        $this->addSql('CREATE INDEX IDX_EF65A5B2A2B28FE8 ON tcc_documents (uploaded_by_id)');
        $this->addSql('CREATE TABLE tcc_status_history (id UUID NOT NULL, from_status VARCHAR(30) DEFAULT NULL, to_status VARCHAR(30) NOT NULL, transition VARCHAR(64) NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, tcc_id UUID NOT NULL, changed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B1EDE05A4F70A726 ON tcc_status_history (tcc_id)');
        $this->addSql('CREATE INDEX IDX_B1EDE05A828AD0A0 ON tcc_status_history (changed_by_id)');
        $this->addSql('CREATE TABLE tccs (id UUID NOT NULL, title VARCHAR(255) NOT NULL, abstract TEXT NOT NULL, status VARCHAR(30) NOT NULL, final_grade NUMERIC(4, 2) DEFAULT NULL, publication_authorized BOOLEAN NOT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, student_id UUID NOT NULL, advisor_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_tccs_status ON tccs (status)');
        $this->addSql('CREATE INDEX IDX_854854CCB944F1A ON tccs (student_id)');
        $this->addSql('CREATE INDEX IDX_854854C66D3AD77 ON tccs (advisor_id)');
        $this->addSql('CREATE TABLE tcc_thematic_areas (tcc_id UUID NOT NULL, thematic_area_id UUID NOT NULL, PRIMARY KEY (tcc_id, thematic_area_id))');
        $this->addSql('CREATE INDEX IDX_466484054F70A726 ON tcc_thematic_areas (tcc_id)');
        $this->addSql('CREATE INDEX IDX_46648405328F8B8E ON tcc_thematic_areas (thematic_area_id)');
        $this->addSql('CREATE TABLE tcc_keywords (tcc_id UUID NOT NULL, keyword_id UUID NOT NULL, PRIMARY KEY (tcc_id, keyword_id))');
        $this->addSql('CREATE INDEX IDX_CDE9D73E4F70A726 ON tcc_keywords (tcc_id)');
        $this->addSql('CREATE INDEX IDX_CDE9D73E115D4552 ON tcc_keywords (keyword_id)');
        $this->addSql('CREATE TABLE thematic_areas (id UUID NOT NULL, name VARCHAR(120) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D8E363915E237E06 ON thematic_areas (name)');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, roles JSONB NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('ALTER TABLE board_members ADD CONSTRAINT FK_DBEFAF04F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE board_members ADD CONSTRAINT FK_DBEFAF043575BE2 FOREIGN KEY (evaluator_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE defense_grades ADD CONSTRAINT FK_714DDFCBFB0C2DCF FOREIGN KEY (defense_id) REFERENCES defenses (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE defense_grades ADD CONSTRAINT FK_714DDFCBC7BA2FD5 FOREIGN KEY (board_member_id) REFERENCES board_members (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE defenses ADD CONSTRAINT FK_3BBAEF4D4F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE defenses ADD CONSTRAINT FK_3BBAEF4DE89A792F FOREIGN KEY (scheduled_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D34F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tcc_documents ADD CONSTRAINT FK_EF65A5B24F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tcc_documents ADD CONSTRAINT FK_EF65A5B2A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tcc_status_history ADD CONSTRAINT FK_B1EDE05A4F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tcc_status_history ADD CONSTRAINT FK_B1EDE05A828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tccs ADD CONSTRAINT FK_854854CCB944F1A FOREIGN KEY (student_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tccs ADD CONSTRAINT FK_854854C66D3AD77 FOREIGN KEY (advisor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE tcc_thematic_areas ADD CONSTRAINT FK_466484054F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tcc_thematic_areas ADD CONSTRAINT FK_46648405328F8B8E FOREIGN KEY (thematic_area_id) REFERENCES thematic_areas (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tcc_keywords ADD CONSTRAINT FK_CDE9D73E4F70A726 FOREIGN KEY (tcc_id) REFERENCES tccs (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tcc_keywords ADD CONSTRAINT FK_CDE9D73E115D4552 FOREIGN KEY (keyword_id) REFERENCES keywords (id) ON DELETE CASCADE NOT DEFERRABLE');

        // Regras que o Doctrine não mapeia: garantidas também no banco
        $this->addSql('ALTER TABLE defense_grades ADD CONSTRAINT chk_defense_grades_range CHECK (project_grade BETWEEN 0 AND 10 AND presentation_grade BETWEEN 0 AND 10)');
        $this->addSql('ALTER TABLE defenses ADD CONSTRAINT chk_defenses_grades_range CHECK ((final_grade IS NULL OR final_grade BETWEEN 0 AND 10) AND (project_average IS NULL OR project_average BETWEEN 0 AND 10) AND (presentation_average IS NULL OR presentation_average BETWEEN 0 AND 10))');
        $this->addSql('ALTER TABLE tccs ADD CONSTRAINT chk_tccs_final_grade_range CHECK (final_grade IS NULL OR final_grade BETWEEN 0 AND 10)');
        $this->addSql('ALTER TABLE submission_periods ADD CONSTRAINT chk_submission_periods_dates CHECK (end_date > start_date)');
        $this->addSql('ALTER TABLE settings ADD CONSTRAINT chk_settings_singleton CHECK (id = 1)');
        $this->addSql('ALTER TABLE settings ADD CONSTRAINT chk_settings_board_size CHECK (board_min_size >= 1 AND board_max_size >= board_min_size)');
        $this->addSql('ALTER TABLE settings ADD CONSTRAINT chk_settings_passing_grade CHECK (passing_grade BETWEEN 0 AND 10)');

        // Configurações padrão: banca de 2 a 3 avaliadores, nota mínima 6,0
        $this->addSql('INSERT INTO settings (id, board_min_size, board_max_size, passing_grade) VALUES (1, 2, 3, 6.00)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE board_members DROP CONSTRAINT FK_DBEFAF04F70A726');
        $this->addSql('ALTER TABLE board_members DROP CONSTRAINT FK_DBEFAF043575BE2');
        $this->addSql('ALTER TABLE defense_grades DROP CONSTRAINT FK_714DDFCBFB0C2DCF');
        $this->addSql('ALTER TABLE defense_grades DROP CONSTRAINT FK_714DDFCBC7BA2FD5');
        $this->addSql('ALTER TABLE defenses DROP CONSTRAINT FK_3BBAEF4D4F70A726');
        $this->addSql('ALTER TABLE defenses DROP CONSTRAINT FK_3BBAEF4DE89A792F');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D34F70A726');
        $this->addSql('ALTER TABLE tcc_documents DROP CONSTRAINT FK_EF65A5B24F70A726');
        $this->addSql('ALTER TABLE tcc_documents DROP CONSTRAINT FK_EF65A5B2A2B28FE8');
        $this->addSql('ALTER TABLE tcc_status_history DROP CONSTRAINT FK_B1EDE05A4F70A726');
        $this->addSql('ALTER TABLE tcc_status_history DROP CONSTRAINT FK_B1EDE05A828AD0A0');
        $this->addSql('ALTER TABLE tccs DROP CONSTRAINT FK_854854CCB944F1A');
        $this->addSql('ALTER TABLE tccs DROP CONSTRAINT FK_854854C66D3AD77');
        $this->addSql('ALTER TABLE tcc_thematic_areas DROP CONSTRAINT FK_466484054F70A726');
        $this->addSql('ALTER TABLE tcc_thematic_areas DROP CONSTRAINT FK_46648405328F8B8E');
        $this->addSql('ALTER TABLE tcc_keywords DROP CONSTRAINT FK_CDE9D73E4F70A726');
        $this->addSql('ALTER TABLE tcc_keywords DROP CONSTRAINT FK_CDE9D73E115D4552');
        $this->addSql('DROP TABLE board_members');
        $this->addSql('DROP TABLE defense_grades');
        $this->addSql('DROP TABLE defenses');
        $this->addSql('DROP TABLE keywords');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE submission_periods');
        $this->addSql('DROP TABLE tcc_documents');
        $this->addSql('DROP TABLE tcc_status_history');
        $this->addSql('DROP TABLE tccs');
        $this->addSql('DROP TABLE tcc_thematic_areas');
        $this->addSql('DROP TABLE tcc_keywords');
        $this->addSql('DROP TABLE thematic_areas');
        $this->addSql('DROP TABLE users');
    }
}
