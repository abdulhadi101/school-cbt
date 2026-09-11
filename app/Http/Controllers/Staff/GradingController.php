<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\GradeAnswerRequest;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Services\Grading\ManualGrader;
use App\Services\Results\AttemptReleaser;
use App\Services\Results\AttemptResultPresenter;
use Illuminate\Http\JsonResponse;

class GradingController extends Controller
{
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
