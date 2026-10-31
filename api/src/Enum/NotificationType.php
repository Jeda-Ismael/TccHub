<?php

declare(strict_types=1);

namespace App\Enum;

/** Ver docs/ARQUITETURA.md §9 para quando cada uma é enviada. */
enum NotificationType: string
{
    case TccSubmitted = 'TCC_SUBMITTED';
    case BoardAssigned = 'BOARD_ASSIGNED';
    case EvaluationOpen = 'EVALUATION_OPEN';
    case CorrectionsRequested = 'CORRECTIONS_REQUESTED';
    case FinalReviewPending = 'FINAL_REVIEW_PENDING';
    case DefensePending = 'DEFENSE_PENDING';
    case DefenseScheduled = 'DEFENSE_SCHEDULED';
    case GradesPending = 'GRADES_PENDING';
    case DecisionPending = 'DECISION_PENDING';
    case TccApproved = 'TCC_APPROVED';
    case TccRejected = 'TCC_REJECTED';
    case PublicationRequested = 'PUBLICATION_REQUESTED';
    case PublicationWithdrawn = 'PUBLICATION_WITHDRAWN';
    case TccPublished = 'TCC_PUBLISHED';
    case TccUnpublished = 'TCC_UNPUBLISHED';
}
