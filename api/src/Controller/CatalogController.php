<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\NameRequest;
use App\Entity\Keyword;
use App\Entity\ThematicArea;
use App\Service\CatalogManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * /api/thematic-areas e /api/keywords: leitura para qualquer logado
 * (o aluno escolhe ao cadastrar o TCC), escrita só para a coordenação.
 */
final class CatalogController extends AbstractController
{
    private const CATALOGS = ['thematic-areas' => ThematicArea::class, 'keywords' => Keyword::class];
    private const CATALOG_REQUIREMENT = 'thematic-areas|keywords';

    public function __construct(
        private readonly CatalogManager $catalogs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/{catalog}', name: 'api_catalog_list', requirements: ['catalog' => self::CATALOG_REQUIREMENT], methods: ['GET'])]
    public function list(string $catalog): JsonResponse
    {
        return $this->json(array_map(
            static fn (array $row): array => self::view($row['item'], $row['tccCount']),
            $this->catalogs->listWithUsage(self::CATALOGS[$catalog]),
        ));
    }

    #[Route('/{catalog}', name: 'api_catalog_create', requirements: ['catalog' => self::CATALOG_REQUIREMENT], methods: ['POST'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function create(string $catalog, #[MapRequestPayload] NameRequest $payload): JsonResponse
    {
        $item = $this->catalogs->create(self::CATALOGS[$catalog], $payload->name);

        return $this->json(self::view($item, 0), Response::HTTP_CREATED);
    }

    #[Route('/{catalog}/{id}', name: 'api_catalog_update', requirements: ['catalog' => self::CATALOG_REQUIREMENT, 'id' => Requirement::UUID], methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function update(string $catalog, string $id, #[MapRequestPayload] NameRequest $payload): JsonResponse
    {
        $item = $this->find($catalog, $id);
        $this->catalogs->rename($item, $payload->name);

        return $this->json(self::view($item, null));
    }

    #[Route('/{catalog}/{id}', name: 'api_catalog_delete', requirements: ['catalog' => self::CATALOG_REQUIREMENT, 'id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function delete(string $catalog, string $id): Response
    {
        $this->catalogs->delete($this->find($catalog, $id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $catalog, string $id): ThematicArea|Keyword
    {
        return $this->em->find(self::CATALOGS[$catalog], Uuid::fromString($id)) ?? throw new NotFoundHttpException();
    }

    /** @return array{id: string, name: string, tccCount?: int} */
    private static function view(ThematicArea|Keyword $item, ?int $tccCount): array
    {
        $view = ['id' => $item->getId()->toRfc4122(), 'name' => $item->getName()];
        if (null !== $tccCount) {
            $view['tccCount'] = $tccCount;
        }

        return $view;
    }
}
