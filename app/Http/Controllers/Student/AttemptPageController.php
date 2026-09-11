<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Services\Attempts\AttemptTakingPresenter;
use Inertia\Inertia;
use Inertia\Response;

class AttemptPageController extends Controller
{
    public function show(Attempt $attempt, AttemptTakingPresenter $presenter): Response
    {
        $user = request()->user();

        if (! $user->student || (int) $user->student->id !== (int) $attempt->student_id) {
            abort(403, 'Forbidden.');
        }

        if ($attempt->status !== AttemptStatus::InProgress) {
            abort(409, 'This attempt is no longer in progress.');
        }

        return Inertia::render('Student/Attempts/Take', [
            'attempt' => $presenter->present($attempt),
        ]);
    }
}
