<?php

namespace App\Enums;

enum ScoreReleasePolicy: string
{
    case Immediate = 'immediate';
    case AfterClose = 'after_close';
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
