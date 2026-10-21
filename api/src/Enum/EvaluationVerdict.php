<?php

declare(strict_types=1);

namespace App\Enum;

enum EvaluationVerdict: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case CorrectionsRequested = 'CORRECTIONS_REQUESTED';
}
