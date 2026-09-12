<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\GradeAnswerRequest;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Services\Grading\ManualGrader;
use App\Services\Results\AttemptReleaser;
use App\Services\Results\AttemptResultPresenter;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradingController extends Controller
{
    public function index(): Response
    {
        $exams = Exam::query()
            ->whereHas('attempts', function ($q) {
                $q->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Grading]);
            })
            ->withCount(['attempts as pending_count' => function ($q) {
                $q->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Grading]);
            }])
            ->withCount(['attempts as graded_count' => function ($q) {
                $q->where('status', AttemptStatus::Graded);
            }])
            ->withCount('attempts as total_count')
            ->with('subject:id,name')
            ->orderByDesc('pending_count')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject?->name,
                'pending_count' => $exam->pending_count,
                'graded_count' => $exam->graded_count,
                'total_count' => $exam->total_count,
            ]);

        return Inertia::render('Staff/Grading/Index', [
            'exams' => $exams,
        ]);
    }

    public function gradePage(Exam $exam): Response
    {
        $attempts = $exam->attempts()
            ->with('student:id,first_name,last_name,admission_number')
            ->withCount(['answers as needs_grading_count' => function ($q) {
                $q->whereIn('grading_status', [GradingStatus::Ungraded, GradingStatus::NeedsGrading]);
            }])
            ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Grading, AttemptStatus::Graded])
            ->get()
            ->map(fn (Attempt $attempt) => [
                'id' => $attempt->id,
                'student_name' => trim(($attempt->student?->first_name ?? '').' '.($attempt->student?->last_name ?? '')),
                'admission_number' => $attempt->student?->admission_number,
                'status' => $attempt->status->value,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'percentage' => $attempt->percentage,
                'needs_grading' => in_array($attempt->status->value, ['submitted', 'grading']) || $attempt->needs_grading_count > 0,
            ]);

        $pendingCount = $attempts->where('needs_grading', true)->count();

        return Inertia::render('Staff/Grading/Exam', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject?->name,
            ],
            'attempts' => $attempts->values()->all(),
            'pendingCount' => $pendingCount,
        ]);
    }

    public function attempt(Attempt $attempt, AttemptResultPresenter $presenter): Response
    {
        $data = $presenter->presentForStaff($attempt->fresh());

        $student = $attempt->student;
        $data['student_name'] = trim(($student?->first_name ?? '').' '.($student?->last_name ?? ''));
        $data['admission_number'] = $student?->admission_number;
        $data['exam_title'] = $attempt->exam?->title;

        return Inertia::render('Staff/Grading/Attempt', $data);
    }

    public function grade(
        GradeAnswerRequest $request,
        AttemptAnswer $answer,
        ManualGrader $grader
    ): JsonResponse {
        $data = $request->validated();

        try {
            $graded = $grader->grade(
                $answer,
                $request->user(),
                (float) $data['score'],
                $data['feedback'] ?? null,
                $data['reason'] ?? null
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'attempt_answer_id' => $graded->id,
            'grading_status' => $graded->grading_status->value,
            'score' => $graded->score,
        ]);
    }

    public function showAttempt(Attempt $attempt, AttemptResultPresenter $presenter): JsonResponse
    {
        return response()->json($presenter->presentForStaff($attempt->fresh()));
    }

    public function release(Attempt $attempt, AttemptReleaser $releaser): JsonResponse
    {
        try {
            $released = $releaser->release($attempt, request()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'attempt_id' => $released->id,
            'status' => $released->status->value,
        ]);
    }
}
