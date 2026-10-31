<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Keyword;
use App\Entity\Tcc;
use App\Entity\ThematicArea;
use App\Entity\User;
use App\Enum\EvaluationVerdict;
use App\Enum\PeriodType;
use App\Enum\Role;
use App\Enum\TccStatus;
use App\Exception\DomainRuleViolation;
use App\Exception\TransitionNotAllowed;
use App\Repository\SettingsRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Casos de uso do TCC até a banca. Permissões (quem) ficam no TccVoter,
 * chamado pelo controller; aqui ficam as regras (quando e como).
 */
final class TccService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Target('tcc')] private readonly WorkflowInterface $workflow,
        private readonly UserRepository $users,
        private readonly SettingsRepository $settings,
        private readonly PeriodChecker $periods,
        private readonly DocumentStorage $storage,
    ) {
    }

    /**
     * @param list<string> $thematicAreaIds
     * @param list<string> $keywordIds
     */
    public function create(User $student, string $title, string $abstract, ?string $advisorId, array $thematicAreaIds, array $keywordIds): Tcc
    {
        if (!$this->periods->isOpen(PeriodType::Submission)) {
            throw new DomainRuleViolation('Não há prazo de submissão de TCC aberto no momento.');
        }

        $tcc = new Tcc($title, $abstract, $student);
        $tcc->changeAdvisor($this->findAdvisor($advisorId));
        $tcc->replaceThematicAreas($this->findAll(ThematicArea::class, $thematicAreaIds, 'Área temática'));
        $tcc->replaceKeywords($this->findAll(Keyword::class, $keywordIds, 'Palavra-chave'));
        $tcc->recordStatusChange(null, TccStatus::Draft, 'create', $student, 'TCC criado');

        $this->em->persist($tcc);
        $this->em->flush();

        return $tcc;
    }

    /**
     * Campos ausentes (null) ficam como estão; advisorId "" remove o orientador.
     *
     * @param list<string>|null $thematicAreaIds
     * @param list<string>|null $keywordIds
     */
    public function update(Tcc $tcc, ?string $title, ?string $abstract, ?string $advisorId, ?array $thematicAreaIds, ?array $keywordIds): void
    {
        if (null !== $title) {
            $tcc->changeTitle($title);
        }
        if (null !== $abstract) {
            $tcc->changeAbstract($abstract);
        }
        if (null !== $advisorId) {
            $tcc->changeAdvisor($this->findAdvisor('' === $advisorId ? null : $advisorId));
        }
        if (null !== $thematicAreaIds) {
            $tcc->replaceThematicAreas($this->findAll(ThematicArea::class, $thematicAreaIds, 'Área temática'));
        }
        if (null !== $keywordIds) {
            $tcc->replaceKeywords($this->findAll(Keyword::class, $keywordIds, 'Palavra-chave'));
        }

        $this->em->flush();
    }

    public function uploadDocument(Tcc $tcc, File $file, string $originalName, User $uploadedBy): void
    {
        $size = (int) $file->getSize();
        $sha256 = (string) hash_file('sha256', $file->getPathname());
        $path = $this->storage->store($tcc, $file);

        try {
            $tcc->addDocument($path, $originalName, $size, $sha256, $uploadedBy);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->storage->discard($path);
            throw $e;
        }
    }

    /** Primeiro envio (rascunho) ou reenvio depois de correções. */
    public function submit(Tcc $tcc): void
    {
        $transition = TccStatus::CorrectionsRequested === $tcc->getStatus() ? 'resubmit' : 'submit';
        $this->apply($tcc, $transition);
        $this->em->flush();
    }

    /**
     * Na entrada (SUBMITTED/RESUBMITTED): aprovar envia para a banca, reprovar encerra.
     * Na revisão final: aprovar segue para a defesa, reprovar devolve para correções.
     */
    public function advisorReview(Tcc $tcc, bool $approve, ?string $comment): void
    {
        $comment = null === $comment ? null : trim($comment);
        if (!$approve && (null === $comment || '' === $comment)) {
            throw new DomainRuleViolation('Explique o motivo no comentário.');
        }

        $transition = match (true) {
            TccStatus::AdvisorFinalReview === $tcc->getStatus() => $approve ? 'final_approve' : 'final_request_corrections',
            default => $approve ? 'advisor_approve' : 'advisor_reject',
        };

        $this->apply($tcc, $transition, $comment);
        $this->em->flush();
    }

    /**
     * Define a banca inteira (adiciona e remove o que mudou). Pareceres de quem
     * continua são mantidos; se a troca completar os pareceres, a rodada fecha.
     *
     * @param list<string> $evaluatorIds
     */
    public function assignBoard(Tcc $tcc, array $evaluatorIds): void
    {
        $evaluatorIds = array_values(array_unique($evaluatorIds));
        $max = $this->settings->get()->getBoardMaxSize();
        if (\count($evaluatorIds) > $max) {
            throw new DomainRuleViolation(\sprintf('A banca pode ter no máximo %d avaliadores.', $max));
        }

        $wanted = [];
        foreach ($evaluatorIds as $id) {
            $evaluator = $this->users->find($this->uuid($id, 'Avaliador'))
                ?? throw new DomainRuleViolation('Avaliador não encontrado.');
            $wanted[$evaluator->getId()->toRfc4122()] = $evaluator;
        }

        foreach ($tcc->getBoardMembers()->toArray() as $member) {
            if (!isset($wanted[$member->getEvaluator()->getId()->toRfc4122()])) {
                $tcc->removeBoardMember($member->getEvaluator());
            }
        }
        foreach ($wanted as $evaluator) {
            if (null === $tcc->findBoardMember($evaluator)) {
                $tcc->addBoardMember($evaluator);
            }
        }

        if (TccStatus::SentToEvaluation === $tcc->getStatus()) {
            if ($tcc->getBoardMembers()->isEmpty()) {
                throw new DomainRuleViolation('Um TCC em avaliação precisa ter pelo menos um avaliador.');
            }
            $this->closeRoundIfComplete($tcc);
        }

        $this->em->flush();
    }

    /**
     * Parecer de um avaliador. Roda numa transação com o TCC travado: se dois
     * avaliadores votarem juntos, o segundo espera e vê o voto do primeiro.
     */
    public function evaluate(Tcc $tcc, User $evaluator, EvaluationVerdict $verdict, ?string $comment): void
    {
        if (!$this->periods->isOpen(PeriodType::Evaluation)) {
            throw new DomainRuleViolation('Não há prazo de avaliação aberto no momento.');
        }

        $this->em->wrapInTransaction(function () use ($tcc, $evaluator, $verdict, $comment): void {
            $this->em->lock($tcc, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($tcc);

            if (TccStatus::SentToEvaluation !== $tcc->getStatus()) {
                throw new TransitionNotAllowed('Este TCC não está mais em avaliação pela banca.');
            }

            $member = $tcc->findBoardMember($evaluator)
                ?? throw new DomainRuleViolation('Você não faz parte da banca deste TCC.');
            $member->giveVerdict($verdict, $comment);

            $this->closeRoundIfComplete($tcc);
            $this->em->flush();
        });
    }

    /** Quando todos deram parecer: alguma correção devolve ao aluno; senão, vai para a revisão final. */
    private function closeRoundIfComplete(Tcc $tcc): void
    {
        $members = $tcc->getBoardMembers();
        if ($members->isEmpty() || $members->exists(static fn ($i, $m): bool => !$m->hasVoted())) {
            return;
        }

        $corrections = $members->filter(static fn ($m): bool => EvaluationVerdict::CorrectionsRequested === $m->getVerdict());
        if ($corrections->isEmpty()) {
            $this->apply($tcc, 'board_approve', 'Banca concluída: todos aprovaram.');

            return;
        }

        $this->apply($tcc, 'board_request_corrections', \sprintf('Banca concluída: %d de %d avaliador(es) pediram correções.', $corrections->count(), $members->count()));
    }

    private function apply(Tcc $tcc, string $transition, ?string $comment = null): void
    {
        try {
            $this->workflow->apply($tcc, $transition, ['comment' => $comment]);
        } catch (NotEnabledTransitionException $e) {
            $messages = [];
            foreach ($e->getTransitionBlockerList() as $blocker) {
                if (TransitionBlocker::BLOCKED_BY_MARKING !== $blocker->getCode()) {
                    $messages[] = $blocker->getMessage();
                }
            }

            throw new TransitionNotAllowed([] !== $messages ? implode(' ', $messages) : \sprintf('Esta ação não é permitida com o TCC em "%s".', $tcc->getStatus()->label()));
        }
    }

    private function findAdvisor(?string $id): ?User
    {
        if (null === $id) {
            return null;
        }

        $advisor = $this->users->find($this->uuid($id, 'Orientador'));
        if (null === $advisor || !$advisor->hasRole(Role::Advisor)) {
            throw new DomainRuleViolation('Orientador não encontrado.');
        }

        return $advisor;
    }

    /**
     * @template T of ThematicArea|Keyword
     *
     * @param class-string<T> $class
     * @param list<string>    $ids
     *
     * @return list<T>
     */
    private function findAll(string $class, array $ids, string $label): array
    {
        $items = [];
        foreach (array_unique($ids) as $id) {
            $items[] = $this->em->find($class, $this->uuid($id, $label))
                ?? throw new DomainRuleViolation(\sprintf('%s não encontrada.', $label));
        }

        return $items;
    }

    private function uuid(string $id, string $label): Uuid
    {
        if (!Uuid::isValid($id)) {
            throw new DomainRuleViolation(\sprintf('%s não encontrado(a).', $label));
        }

        return Uuid::fromString($id);
    }
}
