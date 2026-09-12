<?php

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

arch('application avoids debug helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('controllers are named consistently')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller')
    ->ignoring(Controller::class);

arch('enums are string backed')
    ->expect('App\Enums')
    ->toBeEnums()
    ->toBeStringBackedEnums();

arch('eloquent models extend the base model')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class)
    ->ignoring(User::class);

arch('services do not depend on http controllers')
    ->expect('App\Services')
    ->not->toUse('App\Http\Controllers');
