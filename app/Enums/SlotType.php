<?php

namespace App\Enums;

enum SlotType: string
{
    case FixedQuestion = 'fixed_question';
    case RandomPool = 'random_pool';
}
