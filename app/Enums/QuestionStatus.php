<?php

namespace App\Enums;

enum QuestionStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Retired = 'retired';
}
