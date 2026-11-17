<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Tcc;
use Doctrine\DBAL\Connection;

/**
 * Índice full-text do repositório (tabela `tcc_search`, ver a migration
 * Version20261031190000). Só TCCs publicados ficam no índice.
 *
 * Pesos: A = título · B = palavras-chave e áreas · C = resumo · D = aluno e orientador.
 * Lê do banco, então deve rodar depois do flush dos dados do TCC.
 */
final class RepositoryIndex
{
    public const CONFIG = 'pt_unaccent';

    private const UPSERT = <<<'SQL'
        INSERT INTO tcc_search (tcc_id, document)
        SELECT t.id,
               setweight(to_tsvector('pt_unaccent', t.title), 'A')
            || setweight(to_tsvector('pt_unaccent', concat_ws(' ',
                   (SELECT string_agg(k.name, ' ') FROM tcc_keywords tk JOIN keywords k ON k.id = tk.keyword_id WHERE tk.tcc_id = t.id),
                   (SELECT string_agg(a.name, ' ') FROM tcc_thematic_areas ta JOIN thematic_areas a ON a.id = ta.thematic_area_id WHERE ta.tcc_id = t.id)
               )), 'B')
            || setweight(to_tsvector('pt_unaccent', t.abstract), 'C')
            || setweight(to_tsvector('pt_unaccent', concat_ws(' ', s.name, adv.name)), 'D')
          FROM tccs t
          JOIN users s ON s.id = t.student_id
          LEFT JOIN users adv ON adv.id = t.advisor_id
         WHERE t.published_at IS NOT NULL %s
        ON CONFLICT (tcc_id) DO UPDATE SET document = EXCLUDED.document
        SQL;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** Coloca, atualiza ou tira o TCC do índice conforme ele esteja publicado. */
    public function sync(Tcc $tcc): void
    {
        $id = $tcc->getId()->toRfc4122();
        if (!$tcc->isPublished()) {
            $this->connection->executeStatement('DELETE FROM tcc_search WHERE tcc_id = ?', [$id]);

            return;
        }
        $this->connection->executeStatement(\sprintf(self::UPSERT, 'AND t.id = ?'), [$id]);
    }

    /**
     * Reindexa os TCCs publicados que usam esta área ou palavra-chave (depois de renomear).
     *
     * @param 'tcc_keywords'|'tcc_thematic_areas' $joinTable
     * @param 'keyword_id'|'thematic_area_id'     $column
     */
    public function syncUsing(string $joinTable, string $column, string $itemId): void
    {
        $this->connection->executeStatement(
            \sprintf(self::UPSERT, \sprintf('AND t.id IN (SELECT tcc_id FROM %s WHERE %s = ?)', $joinTable, $column)),
            [$itemId],
        );
    }

    /** Refaz o índice de todos os publicados (manutenção). */
    public function rebuild(): void
    {
        $this->connection->executeStatement('DELETE FROM tcc_search WHERE tcc_id NOT IN (SELECT id FROM tccs WHERE published_at IS NOT NULL)');
        $this->connection->executeStatement(\sprintf(self::UPSERT, ''));
    }
}
