<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/** Sininho: cada usuário vê e marca só os próprios avisos. */
#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    private const LIMIT = 30;

    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $items = $this->notifications->findLatestFor($user, $request->query->getBoolean('unread'), self::LIMIT);
        $boardTccIds = $this->notifications->tccIdsWhereOnBoard($user, $items);

        return $this->json([
            'items' => array_map(fn (Notification $n): array => $this->view($n, $user, $boardTccIds), $items),
            'unreadCount' => $this->notifications->countUnreadFor($user),
        ]);
    }

    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['unreadCount' => $this->notifications->countUnreadFor($user)]);
    }

    #[Route('/{id}/read', name: 'api_notifications_read', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function read(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $notification = $this->notifications->find(Uuid::fromString($id));
        if (null === $notification || !$notification->getRecipient()->getId()->equals($user->getId())) {
            throw new NotFoundHttpException('Notificação não encontrada.');
        }
        $notification->markAsRead($this->clock->now());
        $this->em->flush();

        return $this->json(['unreadCount' => $this->notifications->countUnreadFor($user)]);
    }

    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function readAll(#[CurrentUser] User $user): JsonResponse
    {
        $this->notifications->markAllReadFor($user, $this->clock->now());

        return $this->json(['unreadCount' => 0]);
    }

    /**
     * @param array<string, true> $boardTccIds
     *
     * @return array<string, mixed>
     */
    private function view(Notification $n, User $user, array $boardTccIds): array
    {
        $tz = new \DateTimeZone($this->timezone);
        $tcc = $n->getTcc();

        return [
            'id' => $n->getId()->toRfc4122(),
            'type' => $n->getType()->value,
            'title' => $n->getTitle(),
            'body' => $n->getBody(),
            'tccId' => $tcc?->getId()->toRfc4122(),
            // Com que papel abrir o TCC (a tela muda conforme o vínculo de quem recebeu)
            'as' => null === $tcc ? null : match (true) {
                $tcc->isAuthoredBy($user) => Role::Student->apiName(),
                isset($boardTccIds[$tcc->getId()->toRfc4122()]) => Role::Evaluator->apiName(),
                $tcc->getAdvisor()?->getId()->equals($user->getId()) ?? false => Role::Advisor->apiName(),
                default => Role::Coordination->apiName(),
            },
            'read' => $n->isRead(),
            'createdAt' => $n->getCreatedAt()->setTimezone($tz)->format(\DATE_ATOM),
        ];
    }
}
