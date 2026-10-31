<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Tcc;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Busca no repositório: só TCCs publicados (os que estão em `tcc_search`).
 * Com texto, ordena pela relevância; sem texto, pelos publicados mais recentes.
 */
final class RepositorySearch
{
    public const PAGE_SIZE = 12;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    /**
     * @return array{items: list<Tcc>, total: int}
     */
    public function search(?string $text, ?Uuid $areaId, ?Uuid $keywordId, ?int $year, int $page): array
    {
        $text = null === $text ? '' : trim($text);
        $where = ['TRUE'];
        $params = ['limit' => self::PAGE_SIZE, 'offset' => (max(1, $page) - 1) * self::PAGE_SIZE];
        $order = 't.published_at DESC, t.id DESC';

        if ('' !== $text) {
            $where[] = "ts.document @@ websearch_to_tsquery('".RepositoryIndex::CONFIG."', :q)";
            $order = "ts_rank_cd(ts.document, websearch_to_tsquery('".RepositoryIndex::CONFIG."', :q)) DESC, ".$order;
            $params['q'] = $text;
        }
        if (null !== $areaId) {
            $where[] = 'EXISTS (SELECT 1 FROM tcc_thematic_areas ta WHERE ta.tcc_id = t.id AND ta.thematic_area_id = :area)';
            $params['area'] = $areaId->toRfc4122();
        }
        if (null !== $keywordId) {
            $where[] = 'EXISTS (SELECT 1 FROM tcc_keywords tk WHERE tk.tcc_id = t.id AND tk.keyword_id = :keyword)';
            $params['keyword'] = $keywordId->toRfc4122();
        }
        if (null !== $year) {
            $where[] = 'EXTRACT(YEAR FROM t.published_at AT TIME ZONE :tz) = :year';
            $params['tz'] = $this->timezone;
            $params['year'] = $year;
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT t.id, COUNT(*) OVER () AS total
               FROM tccs t
               JOIN tcc_search ts ON ts.tcc_id = t.id
              WHERE t.published_at IS NOT NULL AND '.implode(' AND ', $where).'
              ORDER BY '.$order.'
              LIMIT :limit OFFSET :offset',
            $params,
        );
        if ([] === $rows) {
            // Página além do fim: conta de novo só para devolver o total certo
            return ['items' => [], 'total' => $page > 1 ? $this->count($where, $params) : 0];
        }

        $ids = array_map(static fn (array $row): string => (string) $row['id'], $rows);

        return ['items' => $this->load($ids), 'total' => (int) $rows[0]['total']];
    }

    /** @return list<int> anos com TCC publicado, do mais recente ao mais antigo */
    public function years(): array
    {
        return array_map('intval', $this->em->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT EXTRACT(YEAR FROM t.published_at AT TIME ZONE :tz)::int AS y
               FROM tccs t JOIN tcc_search ts ON ts.tcc_id = t.id
              WHERE t.published_at IS NOT NULL ORDER BY y DESC',
            ['tz' => $this->timezone],
        ));
    }

    public function findPublished(Uuid $id): ?Tcc
    {
        $tcc = $this->load([$id->toRfc4122()])[0] ?? null;

        return null !== $tcc && $tcc->isPublished() ? $tcc : null;
    }

    /**
     * Carrega os TCCs com o que a resposta usa, numa consulta, mantendo a ordem dos ids.
     *
     * @param list<string> $ids
     *
     * @return list<Tcc>
     */
    private function load(array $ids): array
    {
        /** @var list<Tcc> $tccs */
        $tccs = $this->em->createQueryBuilder()
            ->select('t', 's', 'adv', 'a', 'k', 'd')
            ->from(Tcc::class, 't')
            ->join('t.student', 's')
            ->leftJoin('t.advisor', 'adv')
            ->leftJoin('t.thematicAreas', 'a')
            ->leftJoin('t.keywords', 'k')
            ->leftJoin('t.documents', 'd')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($tccs as $tcc) {
            $byId[$tcc->getId()->toRfc4122()] = $tcc;
        }

        return array_values(array_filter(array_map(static fn (string $id): ?Tcc => $byId[$id] ?? null, $ids)));
    }

    /**
     * @param list<string>         $where
     * @param array<string, mixed> $params
     */
    private function count(array $where, array $params): int
    {
        unset($params['limit'], $params['offset']);

        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM tccs t JOIN tcc_search ts ON ts.tcc_id = t.id
              WHERE t.published_at IS NOT NULL AND '.implode(' AND ', $where),
            $params,
        );
    }
}
