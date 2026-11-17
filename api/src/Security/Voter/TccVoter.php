<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TccStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Quem pode fazer o quê em um TCC. Um professor pode ter vários papéis:
 * vale o vínculo com o TCC (orientador dele, membro da banca), não o
 * papel escolhido na tela.
 *
 * @extends Voter<string, Tcc>
 */
final class TccVoter extends Voter
{
    public const VIEW = 'TCC_VIEW';
    public const EDIT = 'TCC_EDIT';
    public const UPLOAD = 'TCC_UPLOAD';
    public const SUBMIT = 'TCC_SUBMIT';
    public const ADVISOR_REVIEW = 'TCC_ADVISOR_REVIEW';
    public const EVALUATE = 'TCC_EVALUATE';
    public const MANAGE_BOARD = 'TCC_MANAGE_BOARD';
    public const SCHEDULE_DEFENSE = 'TCC_SCHEDULE_DEFENSE';
    public const GRADE_DEFENSE = 'TCC_GRADE_DEFENSE';
    public const DECIDE = 'TCC_DECIDE';

    public const ATTRIBUTES = [
        self::VIEW, self::EDIT, self::UPLOAD, self::SUBMIT, self::ADVISOR_REVIEW, self::EVALUATE, self::MANAGE_BOARD,
        self::SCHEDULE_DEFENSE, self::GRADE_DEFENSE, self::DECIDE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Tcc && \in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isAuthor = $subject->isAuthoredBy($user);
        $isCoordination = $user->hasRole(Role::Coordination);
        $editableByStudent = $subject->getStatus()->isEditableByStudent();

        return match ($attribute) {
            self::VIEW => $isCoordination || $isAuthor || $subject->isAdvisedBy($user) || null !== $subject->findBoardMember($user),
            self::EDIT => ($isAuthor && $editableByStudent) || ($isCoordination && !$subject->getStatus()->isFinal()),
            self::UPLOAD, self::SUBMIT => $isAuthor && $editableByStudent,
            self::ADVISOR_REVIEW => $subject->isAdvisedBy($user)
                && \in_array($subject->getStatus(), [TccStatus::Submitted, TccStatus::Resubmitted, TccStatus::AdvisorFinalReview], true),
            // Só quem ainda não votou nesta rodada (o voto não é alterado depois de ver os colegas)
            self::EVALUATE => TccStatus::SentToEvaluation === $subject->getStatus()
                && false === $subject->findBoardMember($user)?->hasVoted(),
            self::MANAGE_BOARD => $isCoordination && !$subject->getStatus()->isBoardLocked(),
            // Orientador do TCC ou coordenação; reagendar só enquanto ninguém lançou nota
            self::SCHEDULE_DEFENSE => ($subject->isAdvisedBy($user) || $isCoordination)
                && TccStatus::AwaitingDefense === $subject->getStatus()
                && true === $subject->getCurrentDefense()?->getGrades()->isEmpty(),
            // Membro da banca, com a defesa já agendada (o horário é conferido no serviço)
            self::GRADE_DEFENSE => null !== $subject->findBoardMember($user)
                && TccStatus::AwaitingDefense === $subject->getStatus()
                && true === $subject->getCurrentDefense()?->isScheduled(),
            self::DECIDE => $isCoordination && TccStatus::AwaitingDecision === $subject->getStatus(),
            default => false,
        };
    }
}
