<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\BoardMember;
use App\Entity\Tcc;
use App\Entity\TccDocument;
use App\Entity\TccStatusHistory;
use App\Entity\User;
use App\Enum\EvaluationVerdict;
use App\Enum\PeriodType;
use App\Enum\TccStatus;
use App\Security\Voter\TccVoter;
use App\Service\PeriodChecker;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Formato do TCC nas respostas da API, ajustado a quem está vendo:
 * - avaliação cega: enquanto o avaliador não deu o próprio parecer, não vê os dos colegas;
 * - o aluno só vê os pareceres depois que a banca termina a rodada;
 * - os links de download são assinados e expiram em 5 minutos;
 * - `permissions` diz quais ações a tela pode oferecer.
 */
final class TccView implements ResetInterface
{
    private const DOWNLOAD_LINK_TTL = 300;

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorization,
        private readonly UrlGeneratorInterface $urls,
        private readonly UriSigner $signer,
        private readonly PeriodChecker $periods,
    ) {
    }

    /**
     * Consultado uma vez por requisição, não uma vez por TCC da lista. Zerado a
     * cada requisição (ResetInterface): no modo worker o serviço é reaproveitado.
     */
    private ?bool $evaluationPeriodOpen = null;

    public function reset(): void
    {
        $this->evaluationPeriodOpen = null;
    }

    private function isEvaluationPeriodOpen(): bool
    {
        return $this->evaluationPeriodOpen ??= $this->periods->isOpen(PeriodType::Evaluation);
    }

    /** @return array<string, mixed> */
    public function present(Tcc $tcc, User $viewer): array
    {
        $current = $tcc->getCurrentDocument();

        return [
            'id' => $tcc->getId()->toRfc4122(),
            'title' => $tcc->getTitle(),
            'abstract' => $tcc->getAbstract(),
            'status' => $tcc->getStatus()->value,
            'student' => UserView::summary($tcc->getStudent()),
            'advisor' => null === $tcc->getAdvisor() ? null : UserView::summary($tcc->getAdvisor()),
            'thematicAreas' => $tcc->getThematicAreas()->map(static fn ($a): array => ['id' => $a->getId()->toRfc4122(), 'name' => $a->getName()])->getValues(),
            'keywords' => $tcc->getKeywords()->map(static fn ($k): array => ['id' => $k->getId()->toRfc4122(), 'name' => $k->getName()])->getValues(),
            'document' => null === $current ? null : $this->document($current),
            'documents' => array_reverse($tcc->getDocuments()->map($this->document(...))->getValues()),
            'board' => $this->board($tcc, $viewer),
            'statusHistory' => $tcc->getStatusHistory()->map($this->history(...))->getValues(),
            'finalGrade' => null === $tcc->getFinalGrade() ? null : (float) $tcc->getFinalGrade(),
            'publicationAuthorized' => $tcc->isPublicationAuthorized(),
            'publishedAt' => $tcc->getPublishedAt()?->format(\DATE_ATOM),
            'createdAt' => $tcc->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $tcc->getUpdatedAt()->format(\DATE_ATOM),
            'evaluationPeriodOpen' => $this->isEvaluationPeriodOpen(),
            'permissions' => [
                'edit' => $this->authorization->isGranted(TccVoter::EDIT, $tcc),
                'upload' => $this->authorization->isGranted(TccVoter::UPLOAD, $tcc),
                'submit' => $this->authorization->isGranted(TccVoter::SUBMIT, $tcc),
                'advisorReview' => $this->authorization->isGranted(TccVoter::ADVISOR_REVIEW, $tcc),
                // Fora do prazo de avaliação a tela não oferece o formulário (a API recusaria)
                'evaluate' => $this->authorization->isGranted(TccVoter::EVALUATE, $tcc) && $this->isEvaluationPeriodOpen(),
                'manageBoard' => $this->authorization->isGranted(TccVoter::MANAGE_BOARD, $tcc),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function board(Tcc $tcc, User $viewer): array
    {
        $own = $tcc->findBoardMember($viewer);
        $hideOthers = null !== $own && !$own->hasVoted();
        $hideAllFromStudent = $tcc->isAuthoredBy($viewer) && TccStatus::SentToEvaluation === $tcc->getStatus();

        return $tcc->getBoardMembers()->map(static function (BoardMember $member) use ($own, $hideOthers, $hideAllFromStudent): array {
            $hidden = $hideAllFromStudent || ($hideOthers && $member !== $own);

            return [
                'id' => $member->getId()->toRfc4122(),
                'evaluator' => UserView::summary($member->getEvaluator()),
                'verdict' => $hidden ? EvaluationVerdict::Pending->value : $member->getVerdict()->value,
                'comment' => $hidden ? null : $member->getComment(),
                'isMe' => $member === $own,
            ];
        })->getValues();
    }

    /** @return array<string, mixed> */
    private function document(TccDocument $document): array
    {
        $url = $this->urls->generate('api_tcc_document_download', [
            'id' => $document->getTcc()->getId()->toRfc4122(),
            'version' => $document->getVersion(),
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        return [
            'version' => $document->getVersion(),
            'originalName' => $document->getOriginalName(),
            'sizeBytes' => $document->getSizeBytes(),
            'uploadedAt' => $document->getUploadedAt()->format(\DATE_ATOM),
            'downloadUrl' => $this->signer->sign($url, new \DateTimeImmutable('+'.self::DOWNLOAD_LINK_TTL.' seconds')),
        ];
    }

    /** @return array<string, mixed> */
    private function history(TccStatusHistory $entry): array
    {
        return [
            'id' => $entry->getId()->toRfc4122(),
            'fromStatus' => $entry->getFromStatus()?->value,
            'toStatus' => $entry->getToStatus()->value,
            'transition' => $entry->getTransition(),
            'comment' => $entry->getComment(),
            'changedBy' => null === $entry->getChangedBy() ? null : UserView::summary($entry->getChangedBy()),
            'createdAt' => $entry->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
