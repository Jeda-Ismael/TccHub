<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MilestoneRequest;
use App\Entity\Milestone;
use App\Repository\MilestoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Cronograma do TCC: as etapas (projeto, qualificação, versão final...) e suas datas
 * limite. Leitura para todos, escrita para a coordenação.
 */
#[OA\Tag(name: 'Cronograma')]
#[Route('/milestones')]
final class MilestoneController extends AbstractController
{
    public function __construct(
        private readonly MilestoneRepository $milestones,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    /** Em ordem de data. `status`: DONE (já passou), NEXT (a próxima) ou UPCOMING. */
    #[Route('', name: 'api_milestones_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $today = $this->clock->now()->setTimezone(new \DateTimeZone($this->timezone))->format('Y-m-d');
        $nextFound = false;
        $items = [];
        foreach ($this->milestones->findBy([], ['dueDate' => 'ASC', 'name' => 'ASC']) as $milestone) {
            $due = $milestone->getDueDate()->format('Y-m-d');
            $status = $due < $today ? 'DONE' : ($nextFound ? 'UPCOMING' : 'NEXT');
            $nextFound = $nextFound || 'NEXT' === $status;
            $items[] = $this->view($milestone) + ['status' => $status];
        }

        return $this->json($items);
    }

    #[Route('', name: 'api_milestones_create', methods: ['POST'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function create(#[MapRequestPayload] MilestoneRequest $payload): JsonResponse
    {
        $milestone = new Milestone($payload->name, $payload->description, $payload->date($this->timezone));
        $this->em->persist($milestone);
        $this->em->flush();

        return $this->json($this->view($milestone), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_milestones_update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function update(string $id, #[MapRequestPayload] MilestoneRequest $payload): JsonResponse
    {
        $milestone = $this->find($id);
        $milestone->update($payload->name, $payload->description, $payload->date($this->timezone));
        $this->em->flush();

        return $this->json($this->view($milestone));
    }

    #[Route('/{id}', name: 'api_milestones_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function delete(string $id): Response
    {
        $this->em->remove($this->find($id));
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $id): Milestone
    {
        return $this->milestones->find(Uuid::fromString($id)) ?? throw new NotFoundHttpException();
    }

    /** @return array<string, mixed> */
    private function view(Milestone $milestone): array
    {
        return [
            'id' => $milestone->getId()->toRfc4122(),
            'name' => $milestone->getName(),
            'description' => $milestone->getDescription(),
            'dueDate' => $milestone->getDueDate()->format('Y-m-d'),
        ];
    }
}
