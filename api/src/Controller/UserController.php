<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChangeRolesRequest;
use App\Dto\CreateUserRequest;
use App\Dto\SetActiveRequest;
use App\Dto\UserView;
use App\Entity\User;
use App\Enum\Role;
use App\Http\Pagination;
use App\Repository\UserRepository;
use App\Service\UserManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Usuários')]
#[Route('/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserManager $manager,
    ) {
    }

    /**
     * Qualquer usuário logado lista por papel (ex.: aluno escolhendo orientador),
     * vendo só nome e e-mail e só os ativos. Só a coordenação lista todos, vê os
     * papéis e os desativados (`includeInactive` com `role`), busca por nome/e-mail
     * (`q`) e pagina (`page`).
     */
    #[Route('', name: 'api_users_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $currentUser,
        #[MapQueryParameter] ?string $role = null,
        #[MapQueryParameter] ?string $q = null,
        #[MapQueryParameter] ?int $page = null,
        #[MapQueryParameter] ?int $perPage = null,
        #[MapQueryParameter] bool $includeInactive = false,
    ): JsonResponse {
        $isCoordination = $currentUser->hasRole(Role::Coordination);
        if (null === $role && !$isCoordination) {
            throw $this->createAccessDeniedException();
        }

        try {
            $filter = null === $role ? null : Role::fromApiName($role);
        } catch (\ValueError) {
            throw new BadRequestHttpException(\sprintf('Papel desconhecido: "%s".', $role));
        }
        $pagination = Pagination::fromQuery($page, $perPage);

        // Listas por papel servem para escolher alguém: desativados ficam de fora,
        // a não ser na gestão de usuários da coordenação (includeInactive)
        $onlyActive = null !== $filter && !($isCoordination && $includeInactive);
        $result = $this->users->search($filter, $isCoordination ? $q : null, $onlyActive, $isCoordination ? $pagination : null);

        return Pagination::respond(array_map(
            static fn (User $user): array => $isCoordination ? UserView::from($user) : UserView::summary($user),
            $result['items'],
        ), $result['total'], $isCoordination ? $pagination : null);
    }

    /** Desativa (`{"active": false}`) ou reativa um usuário. */
    #[Route('/{id}/active', name: 'api_users_set_active', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function setActive(string $id, #[CurrentUser] User $currentUser, #[MapRequestPayload] SetActiveRequest $payload): JsonResponse
    {
        $user = $this->find($id);
        $this->manager->setActive($user, (bool) $payload->active, $currentUser);

        return $this->json(UserView::from($user));
    }

    #[Route('', name: 'api_users_create', methods: ['POST'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function create(#[MapRequestPayload] CreateUserRequest $payload): JsonResponse
    {
        $user = $this->manager->create($payload->name, $payload->email, $payload->password, $payload->roleList());

        return $this->json(UserView::from($user), Response::HTTP_CREATED);
    }

    #[Route('/{id}/roles', name: 'api_users_change_roles', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function changeRoles(string $id, #[MapRequestPayload] ChangeRolesRequest $payload): JsonResponse
    {
        $user = $this->find($id);
        $this->manager->changeRoles($user, $payload->roleList());

        return $this->json(UserView::from($user));
    }

    #[Route('/{id}', name: 'api_users_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function delete(string $id, #[CurrentUser] User $currentUser): Response
    {
        $this->manager->delete($this->find($id), $currentUser);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $id): User
    {
        return $this->users->find(Uuid::fromString($id)) ?? throw new NotFoundHttpException();
    }
}
