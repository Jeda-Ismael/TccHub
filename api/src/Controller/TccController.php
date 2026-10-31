<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AdvisorReviewRequest;
use App\Dto\BoardRequest;
use App\Dto\EvaluateRequest;
use App\Dto\TccRequest;
use App\Dto\TccView;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TccStatus;
use App\Exception\DomainRuleViolation;
use App\Repository\TccRepository;
use App\Security\Voter\TccVoter;
use App\Service\DocumentStorage;
use App\Service\TccService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/tcc')]
final class TccController extends AbstractController
{
    private const MAX_PDF_SIZE = '20M';

    public function __construct(
        private readonly TccRepository $tccs,
        private readonly TccService $service,
        private readonly TccView $view,
    ) {
    }

    /** ?as=ADVISOR escolhe o papel da listagem (padrão: o primeiro papel do usuário). */
    #[Route('', name: 'api_tcc_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, #[MapQueryParameter] ?string $as = null, #[MapQueryParameter] ?string $status = null): JsonResponse
    {
        try {
            $role = null === $as ? $user->getRoleList()[0] : Role::fromApiName($as);
            $statusFilter = null === $status ? null : TccStatus::from($status);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Filtro inválido.');
        }
        if (!$user->hasRole($role)) {
            throw $this->createAccessDeniedException();
        }

        return $this->json(array_map(
            fn (Tcc $tcc): array => $this->view->present($tcc, $user),
            $this->tccs->findVisibleAs($user, $role, $statusFilter),
        ));
    }

    #[Route('', name: 'api_tcc_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $user, #[MapRequestPayload(validationGroups: ['create'])] TccRequest $payload): JsonResponse
    {
        if (!$user->hasRole(Role::Student)) {
            throw $this->createAccessDeniedException();
        }

        $tcc = $this->service->create(
            $user,
            (string) $payload->title,
            (string) $payload->abstract,
            '' === $payload->advisorId ? null : $payload->advisorId,
            $payload->thematicAreaIds ?? [],
            $payload->keywordIds ?? [],
        );

        return $this->json($this->view->present($tcc, $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_tcc_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->view->present($this->find($id, TccVoter::VIEW), $user));
    }

    #[Route('/{id}', name: 'api_tcc_update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function update(string $id, #[CurrentUser] User $user, #[MapRequestPayload] TccRequest $payload): JsonResponse
    {
        $tcc = $this->find($id, TccVoter::EDIT);
        $this->service->update($tcc, $payload->title, $payload->abstract, $payload->advisorId, $payload->thematicAreaIds, $payload->keywordIds);

        return $this->json($this->view->present($tcc, $user));
    }

    /** Envio do PDF (multipart, campo "file"). Cada envio vira uma nova versão. */
    #[Route('/{id}/document', name: 'api_tcc_document_upload', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function uploadDocument(string $id, #[CurrentUser] User $user, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $tcc = $this->find($id, TccVoter::UPLOAD);

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new DomainRuleViolation('Envie o arquivo PDF no campo "file".');
        }
        // Confere o conteúdo real do arquivo, não só a extensão
        $violations = $validator->validate($file, new Assert\File(
            maxSize: self::MAX_PDF_SIZE,
            mimeTypes: ['application/pdf'],
            maxSizeMessage: 'O PDF pode ter no máximo 20 MB.',
            mimeTypesMessage: 'Envie um arquivo PDF.',
        ));
        foreach ($violations as $violation) {
            throw new DomainRuleViolation((string) $violation->getMessage());
        }

        $this->service->uploadDocument($tcc, $file, $file->getClientOriginalName(), $user);

        return $this->json($this->view->present($tcc, $user), Response::HTTP_CREATED);
    }

    /**
     * Download por link assinado (gerado só para quem pode ver o TCC e válido por
     * 5 minutos): funciona num <a href> comum, que não envia o token de acesso.
     */
    #[Route('/{id}/documents/{version}/download', name: 'api_tcc_document_download', requirements: ['id' => Requirement::UUID, 'version' => '\d+'], methods: ['GET'])]
    public function download(string $id, int $version, Request $request, UriSigner $signer, DocumentStorage $storage): Response
    {
        try {
            $signer->verify($request->getRequestUri());
        } catch (\Throwable) {
            // 403 mesmo sem login: o link é que vale, não a sessão
            throw new AccessDeniedHttpException('Link de download inválido ou expirado. Abra o TCC de novo para gerar outro.');
        }

        $tcc = $this->tccs->find(Uuid::fromString($id)) ?? throw new NotFoundHttpException();
        $document = $tcc->getDocuments()->findFirst(static fn ($i, $d): bool => $d->getVersion() === $version)
            ?? throw new NotFoundHttpException();

        $response = new BinaryFileResponse($storage->pathOf($document));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $document->getOriginalName(), 'tcc-v'.$document->getVersion().'.pdf');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/{id}/submit', name: 'api_tcc_submit', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function submit(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $tcc = $this->find($id, TccVoter::SUBMIT);
        $this->service->submit($tcc);

        return $this->json($this->view->present($tcc, $user));
    }

    #[Route('/{id}/advisor-review', name: 'api_tcc_advisor_review', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function advisorReview(string $id, #[CurrentUser] User $user, #[MapRequestPayload] AdvisorReviewRequest $payload): JsonResponse
    {
        $tcc = $this->find($id, TccVoter::ADVISOR_REVIEW);
        $this->service->advisorReview($tcc, (bool) $payload->approve, $payload->comment);

        return $this->json($this->view->present($tcc, $user));
    }

    #[Route('/{id}/evaluators', name: 'api_tcc_board', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    public function assignBoard(string $id, #[CurrentUser] User $user, #[MapRequestPayload] BoardRequest $payload): JsonResponse
    {
        $tcc = $this->find($id, TccVoter::MANAGE_BOARD);
        $this->service->assignBoard($tcc, $payload->evaluatorIds);

        return $this->json($this->view->present($tcc, $user));
    }

    #[Route('/{id}/evaluate', name: 'api_tcc_evaluate', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function evaluate(string $id, #[CurrentUser] User $user, #[MapRequestPayload] EvaluateRequest $payload): JsonResponse
    {
        $tcc = $this->find($id, TccVoter::EVALUATE);
        $this->service->evaluate($tcc, $user, $payload->verdictEnum(), $payload->comment);

        return $this->json($this->view->present($tcc, $user));
    }

    private function find(string $id, string $attribute): Tcc
    {
        $tcc = $this->tccs->find(Uuid::fromString($id)) ?? throw new NotFoundHttpException();
        // Quem nem pode ver o TCC recebe 404, sem confirmar que ele existe
        if (!$this->isGranted(TccVoter::VIEW, $tcc)) {
            throw new NotFoundHttpException();
        }
        $this->denyAccessUnlessGranted($attribute, $tcc);

        return $tcc;
    }
}
