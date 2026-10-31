<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\DocumentLink;
use App\Entity\Keyword;
use App\Entity\Tcc;
use App\Entity\ThematicArea;
use App\Search\RepositorySearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Repositório de TCCs publicados, para qualquer usuário logado. Mostra só o que
 * é público: dados do trabalho, nota final e o PDF final. Pareceres, notas por
 * avaliador, histórico e versões anteriores continuam restritos.
 */
#[Route('/repository')]
final class RepositoryController extends AbstractController
{
    public function __construct(
        private readonly RepositorySearch $search,
        private readonly DocumentLink $links,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    #[Route('', name: 'api_repository_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query;
        $text = $query->getString('q');
        if (mb_strlen($text) > 200) {
            throw new UnprocessableEntityHttpException('A busca pode ter no máximo 200 caracteres.');
        }
        $year = '' === $query->getString('year') ? null : $query->getInt('year');
        $page = max(1, $query->getInt('page', 1));

        $result = $this->search->search(
            $text,
            self::uuidFilter($query->getString('area'), 'Área temática'),
            self::uuidFilter($query->getString('keyword'), 'Palavra-chave'),
            $year,
            $page,
        );

        return $this->json([
            'items' => array_map($this->summary(...), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => RepositorySearch::PAGE_SIZE,
            'years' => $this->search->years(),
        ]);
    }

    #[Route('/{id}', name: 'api_repository_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $tcc = $this->search->findPublished(Uuid::fromString($id))
            ?? throw new NotFoundHttpException('TCC não encontrado no repositório.');
        $document = $tcc->getCurrentDocument();

        return $this->json([
            ...$this->summary($tcc),
            'document' => null === $document ? null : [
                'version' => $document->getVersion(),
                'originalName' => $document->getOriginalName(),
                'sizeBytes' => $document->getSizeBytes(),
                'downloadUrl' => $this->links->for($document),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(Tcc $tcc): array
    {
        $advisor = $tcc->getAdvisor();

        return [
            'id' => $tcc->getId()->toRfc4122(),
            'title' => $tcc->getTitle(),
            'abstract' => $tcc->getAbstract(),
            'student' => ['id' => $tcc->getStudent()->getId()->toRfc4122(), 'name' => $tcc->getStudent()->getName()],
            'advisor' => null === $advisor ? null : ['id' => $advisor->getId()->toRfc4122(), 'name' => $advisor->getName()],
            'thematicAreas' => $tcc->getThematicAreas()->map(static fn (ThematicArea $a): array => ['id' => $a->getId()->toRfc4122(), 'name' => $a->getName()])->getValues(),
            'keywords' => $tcc->getKeywords()->map(static fn (Keyword $k): array => ['id' => $k->getId()->toRfc4122(), 'name' => $k->getName()])->getValues(),
            'finalGrade' => null === $tcc->getFinalGrade() ? null : (float) $tcc->getFinalGrade(),
            'publishedAt' => $tcc->getPublishedAt()?->setTimezone(new \DateTimeZone($this->timezone))->format(\DATE_ATOM),
        ];
    }

    private static function uuidFilter(string $value, string $label): ?Uuid
    {
        if ('' === $value) {
            return null;
        }
        if (!Uuid::isValid($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('%s inválida no filtro.', $label));
        }

        return Uuid::fromString($value);
    }
}
