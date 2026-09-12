<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveAnswerRequest;
use App\Http\Requests\Student\StartAttemptRequest;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Exam;
use App\Services\Attempts\AnswerAutosaver;
use App\Services\Attempts\AttemptStarter;
use App\Services\Attempts\AttemptSubmitter;
use App\Services\Results\AttemptResultPresenter;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AttemptController extends Controller
{
    public function start(
        StartAttemptRequest $request,
        AttemptStarter $starter,
        AttemptResultPresenter $presenter
    ): JsonResponse {
        $student = $request->user()->student;

        if (! $student) {
            abort(403, 'No student profile.');
        }

        $exam = Exam::query()->findOrFail($request->validated()['exam_id']);

        try {
            $attempt = $starter->startOrResume($exam, $student);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLogger::log(
            action: 'attempt.started',
            model: $attempt,
            newValues: ['exam_id' => $exam->id],
        );

        return response()->json($presenter->presentForStudent($attempt->fresh()));
    }

    public function saveAnswer(
        SaveAnswerRequest $request,
        Attempt $attempt,
        AnswerAutosaver $autosaver
    ): JsonResponse {
        $this->ensureOwnsAttempt($request->user(), $attempt);

        $data = $request->validated();
        $attemptQuestion = AttemptQuestion::query()->findOrFail($data['attempt_question_id']);

        try {
            $answer = $autosaver->save(
                $attempt,
                $attemptQuestion,
                $data['response'],
                $data['client_sequence'],
                isset($data['client_answered_at']) ? Carbon::parse($data['client_answered_at']) : null,
                $data['time_spent_seconds'] ?? 0
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'attempt_answer_id' => $answer->id,
            'client_sequence' => $answer->client_sequence,
            'answered_at' => $answer->answered_at,
        ]);
    }

    public function submit(
        Attempt $attempt,
        AttemptSubmitter $submitter,
        AttemptResultPresenter $presenter
    ): JsonResponse {
        $this->ensureOwnsAttempt(request()->user(), $attempt);

        try {
            $submitted = $submitter->submit($attempt);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLogger::log(
            action: 'attempt.submitted',
            model: $attempt,
            newValues: ['status' => $submitted->status->value],
        );

        return response()->json($presenter->presentForStudent($submitted->fresh()));
    }

    public function result(Attempt $attempt, AttemptResultPresenter $presenter): JsonResponse
    {
        $user = request()->user();

        $owns = $user->student && (int) $user->student->id === (int) $attempt->student_id;
        $canView = $user->hasPermission('results.view') || $user->hasPermission('grades.view');

        if (! $owns && ! $canView) {
            abort(403, 'Forbidden.');
        }

        return response()->json($presenter->presentForStudent($attempt->fresh()));
    }

    private function ensureOwnsAttempt($user, Attempt $attempt): void
    {
        if (! $user->student || (int) $user->student->id !== (int) $attempt->student_id) {
            abort(403, 'Forbidden.');
        }
    }
}
