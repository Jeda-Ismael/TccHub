<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Keyword;
use App\Entity\ThematicArea;
use App\Exception\DomainRuleViolation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cadastros simples usados pelos TCCs: áreas temáticas e palavras-chave.
 */
final class CatalogManager
{
    private const LABELS = [
        ThematicArea::class => ['singular' => 'área temática', 'join' => 'tcc_thematic_areas', 'column' => 'thematic_area_id'],
        Keyword::class => ['singular' => 'palavra-chave', 'join' => 'tcc_keywords', 'column' => 'keyword_id'],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @template T of ThematicArea|Keyword
     *
     * @param class-string<T> $class
     *
     * @return list<array{item: T, tccCount: int}>
     */
    public function listWithUsage(string $class): array
    {
        $meta = self::LABELS[$class];
        /** @var list<T> $items */
        $items = $this->em->getRepository($class)->findBy([], ['name' => 'ASC']);
        $counts = $this->em->getConnection()->fetchAllKeyValue(
            \sprintf('SELECT %s, COUNT(*) FROM %s GROUP BY %1$s', $meta['column'], $meta['join']),
        );

        return array_map(
            static fn (ThematicArea|Keyword $item): array => ['item' => $item, 'tccCount' => (int) ($counts[$item->getId()->toRfc4122()] ?? 0)],
            $items,
        );
    }

    /**
     * @template T of ThematicArea|Keyword
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function create(string $class, string $name): ThematicArea|Keyword
    {
        $this->assertNameIsFree($class, $name, null);
        $item = new $class($name);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    public function rename(ThematicArea|Keyword $item, string $name): void
    {
        $this->assertNameIsFree($item::class, $name, $item);
        $item->rename($name);
        $this->em->flush();
    }

    /** Não apaga o que está em uso: o TCC perderia a classificação sem ninguém perceber. */
    public function delete(ThematicArea|Keyword $item): void
    {
        $meta = self::LABELS[$item::class];
        $inUse = (int) $this->em->getConnection()->fetchOne(
            \sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $meta['join'], $meta['column']),
            [$item->getId()->toRfc4122()],
        );
        if ($inUse > 0) {
            throw new DomainRuleViolation(\sprintf('Não é possível excluir: esta %s está em uso por %d TCC(s).', $meta['singular'], $inUse));
        }

        $this->em->remove($item);
        $this->em->flush();
    }

    /** @param class-string<ThematicArea|Keyword> $class */
    private function assertNameIsFree(string $class, string $name, ThematicArea|Keyword|null $current): void
    {
        $existing = $this->em->createQueryBuilder()
            ->select('c')
            ->from($class, 'c')
            ->where('LOWER(c.name) = LOWER(:name)')
            ->setParameter('name', trim($name))
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $existing && $existing !== $current) {
            throw new DomainRuleViolation(\sprintf('Já existe uma %s com este nome.', self::LABELS[$class]['singular']));
        }
    }
}
