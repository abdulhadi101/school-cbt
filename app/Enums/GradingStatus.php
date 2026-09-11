<?php

namespace App\Enums;

enum GradingStatus: string
{
    case Ungraded = 'ungraded';
    case AutoGraded = 'auto_graded';
    case NeedsGrading = 'needs_grading';
    case ManuallyGraded = 'manually_graded';
}
