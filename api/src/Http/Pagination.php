<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Paginação opcional das listas (`?page=2&perPage=20`). Sem `page`, a lista
 * vem inteira, como antes. O corpo continua sendo um array; o total vai nos
 * cabeçalhos X-Total-Count, X-Page e X-Per-Page.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    private function __construct(
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public static function fromQuery(?int $page, ?int $perPage): ?self
    {
        if (null === $page) {
            return null;
        }
        $perPage ??= self::DEFAULT_PER_PAGE;
        if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new BadRequestHttpException(\sprintf('Use page a partir de 1 e perPage entre 1 e %d.', self::MAX_PER_PAGE));
        }

        return new self($page, $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Monta a resposta com os cabeçalhos de paginação (quando houver).
     *
     * @param list<mixed> $items
     */
    public static function respond(array $items, int $total, ?self $pagination): JsonResponse
    {
        $response = new JsonResponse($items);
        $response->headers->set('X-Total-Count', (string) $total);
        if (null !== $pagination) {
            $response->headers->set('X-Page', (string) $pagination->page);
            $response->headers->set('X-Per-Page', (string) $pagination->perPage);
        }

        return $response;
    }
}
