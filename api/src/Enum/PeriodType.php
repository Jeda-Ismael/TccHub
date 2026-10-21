<?php

declare(strict_types=1);

namespace App\Enum;

enum PeriodType: string
{
    case Submission = 'SUBMISSION';
    case Evaluation = 'EVALUATION';
}
