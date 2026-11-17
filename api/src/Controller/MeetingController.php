<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MeetingRequest;
use App\Dto\UserView;
use App\Entity\AdvisingMeeting;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Exception\DomainRuleViolation;
use App\Notification\Notifier;
use App\Repository\AdvisingMeetingRepository;
use App\Repository\TccRepository;
use App\Security\Voter\TccVoter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Reuniões de orientação. O orientador registra (data, resumo, próximos passos) e o
 * aluno confirma que a reunião aconteceu. Quem vê o TCC vê as reuniões. Uma reunião
 * confirmada não pode mais ser apagada.
 */
#[OA\Tag(name: 'Reuniões de orientação')]
#[Route('/tcc/{id}/meetings', requirements: ['id' => Requirement::UUID])]
final class MeetingController extends AbstractController
{
    public function __construct(
        private readonly TccRepository $tccs,
        private readonly AdvisingMeetingRepository $meetings,
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    #[Route('', name: 'api_meetings_list', methods: ['GET'])]
    public function list(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $tcc = $this->tcc($id);

        return $this->json(array_map(fn (AdvisingMeeting $m): array => $this->view($m, $user), $this->meetings->forTcc($tcc)));
    }

    #[Route('', name: 'api_meetings_create', methods: ['POST'])]
    public function create(string $id, #[CurrentUser] User $user, #[MapRequestPayload] MeetingRequest $payload): JsonResponse
    {
        $tcc = $this->tcc($id);
        if (!$this->isGranted(TccVoter::RECORD_MEETING, $tcc)) {
            throw new AccessDeniedHttpException('Só o orientador registra reuniões, enquanto o TCC está em andamento.');
        }

        $meeting = new AdvisingMeeting($tcc, $user, $payload->date($this->timezone), $payload->summary, $payload->nextSteps, $this->clock->now());
        $this->em->persist($meeting);
        $this->notifier->notify(NotificationType::MeetingRecorded, $tcc, [$tcc->getStudent()],
            'Reunião de orientação registrada',
            \sprintf('%s registrou a reunião de %s. Confira e confirme que ela aconteceu.', $user->getName(), $meeting->getHeldOn()->format('d/m/Y')));
        $this->em->flush();

        return $this->json($this->view($meeting, $user), Response::HTTP_CREATED);
    }

    #[Route('/{meetingId}/confirm', name: 'api_meetings_confirm', requirements: ['meetingId' => Requirement::UUID], methods: ['POST'])]
    public function confirm(string $id, string $meetingId, #[CurrentUser] User $user): JsonResponse
    {
        $tcc = $this->tcc($id);
        if (!$tcc->isAuthoredBy($user)) {
            throw new AccessDeniedHttpException('Só o aluno do TCC confirma as reuniões.');
        }
        $meeting = $this->meeting($tcc, $meetingId);
        $meeting->confirm($this->clock->now());
        $this->notifier->notify(NotificationType::MeetingConfirmed, $tcc, [$meeting->getAdvisor()],
            'Reunião confirmada pelo aluno',
            \sprintf('%s confirmou a reunião de %s.', $user->getName(), $meeting->getHeldOn()->format('d/m/Y')));
        $this->em->flush();

        return $this->json($this->view($meeting, $user));
    }

    #[Route('/{meetingId}', name: 'api_meetings_delete', requirements: ['meetingId' => Requirement::UUID], methods: ['DELETE'])]
    public function delete(string $id, string $meetingId, #[CurrentUser] User $user): Response
    {
        $tcc = $this->tcc($id);
        $meeting = $this->meeting($tcc, $meetingId);
        if (!$meeting->getAdvisor()->getId()->equals($user->getId())) {
            throw new AccessDeniedHttpException('Só quem registrou a reunião pode apagá-la.');
        }
        if ($meeting->isConfirmed()) {
            throw new DomainRuleViolation('O aluno já confirmou esta reunião; ela não pode mais ser apagada.');
        }
        $this->em->remove($meeting);
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function tcc(string $id): Tcc
    {
        $tcc = $this->tccs->find(Uuid::fromString($id)) ?? throw new NotFoundHttpException();
        // Quem nem pode ver o TCC recebe 404, sem confirmar que ele existe
        if (!$this->isGranted(TccVoter::VIEW, $tcc)) {
            throw new NotFoundHttpException();
        }

        return $tcc;
    }

    private function meeting(Tcc $tcc, string $meetingId): AdvisingMeeting
    {
        $meeting = $this->meetings->find(Uuid::fromString($meetingId));
        if (null === $meeting || $meeting->getTcc() !== $tcc) {
            throw new NotFoundHttpException();
        }

        return $meeting;
    }

    /** @return array<string, mixed> */
    private function view(AdvisingMeeting $m, User $viewer): array
    {
        $tz = new \DateTimeZone($this->timezone);
        $isAuthor = $m->getTcc()->isAuthoredBy($viewer);
        $isRecorder = $m->getAdvisor()->getId()->equals($viewer->getId());

        return [
            'id' => $m->getId()->toRfc4122(),
            'heldOn' => $m->getHeldOn()->format('Y-m-d'),
            'summary' => $m->getSummary(),
            'nextSteps' => $m->getNextSteps(),
            'advisor' => UserView::summary($m->getAdvisor()),
            'confirmedAt' => $m->getConfirmedAt()?->setTimezone($tz)->format(\DATE_ATOM),
            'createdAt' => $m->getCreatedAt()->setTimezone($tz)->format(\DATE_ATOM),
            'permissions' => [
                'confirm' => $isAuthor && !$m->isConfirmed(),
                'delete' => $isRecorder && !$m->isConfirmed(),
            ],
        ];
    }
}
