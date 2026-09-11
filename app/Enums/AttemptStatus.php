<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Expired = 'expired';
    case Grading = 'grading';
    case Graded = 'graded';
    case Released = 'released';
    case Invalidated = 'invalidated';
}
