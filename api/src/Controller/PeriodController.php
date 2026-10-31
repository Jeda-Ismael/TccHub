<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\PeriodRequest;
use App\Entity\SubmissionPeriod;
use App\Repository\SubmissionPeriodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** Prazos de submissão e de avaliação: leitura para todos, escrita para a coordenação. */
#[Route('/periods')]
final class PeriodController extends AbstractController
{
    public function __construct(
        private readonly SubmissionPeriodRepository $periods,
        private readonly EntityManagerInterface $em,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    #[Route('', name: 'api_periods_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $now = new \DateTimeImmutable();

        return $this->json(array_map(
            fn (SubmissionPeriod $period): array => $this->view($period, $now),
            $this->periods->findBy([], ['startDate' => 'DESC']),
        ));
    }

    #[Route('', name: 'api_periods_create', methods: ['POST'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function create(#[MapRequestPayload] PeriodRequest $payload): JsonResponse
    {
        $period = new SubmissionPeriod($payload->typeEnum(), $payload->label, $payload->start($this->timezone), $payload->end($this->timezone));
        $this->em->persist($period);
        $this->em->flush();

        return $this->json($this->view($period, new \DateTimeImmutable()), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_periods_update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function update(string $id, #[MapRequestPayload] PeriodRequest $payload): JsonResponse
    {
        $period = $this->find($id);
        $period->update($payload->typeEnum(), $payload->label, $payload->start($this->timezone), $payload->end($this->timezone));
        $this->em->flush();

        return $this->json($this->view($period, new \DateTimeImmutable()));
    }

    #[Route('/{id}', name: 'api_periods_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function delete(string $id): Response
    {
        $this->em->remove($this->find($id));
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $id): SubmissionPeriod
    {
        return $this->periods->find(Uuid::fromString($id)) ?? throw new NotFoundHttpException();
    }

    /** @return array<string, mixed> */
    private function view(SubmissionPeriod $period, \DateTimeImmutable $now): array
    {
        $zone = new \DateTimeZone($this->timezone);

        return [
            'id' => $period->getId()->toRfc4122(),
            'type' => $period->getType()->value,
            'label' => $period->getLabel(),
            'startDate' => $period->getStartDate()->setTimezone($zone)->format(\DATE_ATOM),
            'endDate' => $period->getEndDate()->setTimezone($zone)->format(\DATE_ATOM),
            'active' => $period->isActiveAt($now),
        ];
    }
}
