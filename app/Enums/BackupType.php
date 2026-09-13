<?php

namespace App\Enums;

enum BackupType: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
