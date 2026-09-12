<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Services\Results\AttemptResultPresenter;
use App\Services\Results\ResultReleasePolicy;
use Inertia\Inertia;
use Inertia\Response;

class StudentResultsController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $student = $user->student;

        if (! $student) {
            abort(403, 'No student profile.');
        }

        $attempts = Attempt::query()
            ->with('exam:id,title,subject_id', 'exam.subject:id,name')
            ->where('student_id', $student->id)
            ->whereIn('status', ['graded', 'released'])
            ->latest('submitted_at')
            ->get()
            ->map(fn (Attempt $attempt) => [
                'id' => $attempt->id,
                'exam_title' => $attempt->exam?->title ?? 'Deleted exam',
                'subject' => $attempt->exam?->subject?->name,
                'status' => $attempt->status->value,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'percentage' => $attempt->percentage,
                'submitted_at' => $attempt->submitted_at?->toISOString(),
                'graded_at' => $attempt->graded_at?->toISOString(),
                'released_at' => $attempt->released_at?->toISOString(),
            ]);

        return Inertia::render('Student/Results/Index', [
            'attempts' => $attempts->values()->all(),
        ]);
    }

    public function show(Attempt $attempt, AttemptResultPresenter $presenter, ResultReleasePolicy $policy): Response
    {
        $user = request()->user();
        $student = $user->student;

        if (! $student || (int) $student->id !== (int) $attempt->student_id) {
            abort(403, 'Forbidden.');
        }

        $attempt->load('exam:id,title,subject_id,score_release_policy,show_responses,show_correct_answers,show_feedback', 'exam.subject:id,name');

        $released = $policy->isScoreReleased($attempt->exam, $attempt);

        if (! $released && ! $user->hasPermission('grades.view')) {
            abort(403, 'This result has not been released yet.');
        }

        $data = $presenter->presentForStudent($attempt->fresh());
        $data['exam_title'] = $attempt->exam?->title;
        $data['subject'] = $attempt->exam?->subject?->name;
        $data['submitted_at'] = $attempt->submitted_at?->toISOString();

        return Inertia::render('Student/Results/Show', $data);
    }
}
