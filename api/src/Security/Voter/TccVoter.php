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

    public const ATTRIBUTES = [self::VIEW, self::EDIT, self::UPLOAD, self::SUBMIT, self::ADVISOR_REVIEW, self::EVALUATE, self::MANAGE_BOARD];

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
            self::EVALUATE => null !== $subject->findBoardMember($user) && TccStatus::SentToEvaluation === $subject->getStatus(),
            self::MANAGE_BOARD => $isCoordination && !$subject->getStatus()->isBoardLocked(),
            default => false,
        };
    }
}
