<?php

declare(strict_types=1);

namespace App\Enum;

enum DefenseOutcome: string
{
    case Pending = 'PENDING';
    case Passed = 'PASSED';
    case Failed = 'FAILED';
}
