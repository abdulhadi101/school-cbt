<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Results\AttemptReleaser;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResultReleaseController extends Controller
{
    public function show(Exam $exam): Response
    {
        $exam->load('subject:id,name');

        $attempts = $exam->attempts()
            ->with('student:id,first_name,last_name,admission_number')
            ->get()
            ->map(fn ($attempt) => [
                'id' => $attempt->id,
                'student_name' => trim(($attempt->student?->first_name ?? '').' '.($attempt->student?->last_name ?? '')),
                'admission_number' => $attempt->student?->admission_number,
                'status' => $attempt->status->value,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'percentage' => $attempt->percentage,
                'attempt_number' => $attempt->attempt_number,
                'submitted_at' => $attempt->submitted_at?->toISOString(),
                'graded_at' => $attempt->graded_at?->toISOString(),
                'released_at' => $attempt->released_at?->toISOString(),
            ]);

        $summary = [
            'total' => $attempts->count(),
            'submitted' => $attempts->where('status', 'submitted')->count(),
            'grading' => $attempts->where('status', 'grading')->count(),
            'graded' => $attempts->where('status', 'graded')->count(),
            'released' => $attempts->where('status', 'released')->count(),
            'average_score' => $attempts->where('status', 'released')->avg('percentage')
                ? round((float) $attempts->where('status', 'released')->avg('percentage'), 1)
                : null,
        ];

        return Inertia::render('Staff/Results/Show', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject?->name,
                'status' => $exam->status->value,
                'score_release_policy' => $exam->score_release_policy,
            ],
            'attempts' => $attempts->values()->all(),
            'summary' => $summary,
        ]);
    }

    public function release(Exam $exam, AttemptReleaser $releaser): JsonResponse
    {
        $attempt = $exam->attempts()->findOrFail(request('attempt_id'));

        try {
            $released = $releaser->release($attempt, request()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'attempt_id' => $released->id,
            'status' => $released->status->value,
            'released_at' => $released->released_at?->toISOString(),
        ]);
    }

    public function releaseAll(Exam $exam, AttemptReleaser $releaser): JsonResponse
    {
        try {
            $count = $releaser->releaseAllForExam($exam, request()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'released_count' => $count,
        ]);
    }
}
