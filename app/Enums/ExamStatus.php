<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';
}
