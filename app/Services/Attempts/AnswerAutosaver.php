<?php

namespace App\Services\Attempts;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnswerAutosaver
{
    public function save(
        Attempt $attempt,
        AttemptQuestion $attemptQuestion,
        array $response,
        int $clientSequence,
        ?Carbon $clientAnsweredAt = null,
        int $timeSpentSeconds = 0,
        string $source = 'web'
    ): AttemptAnswer {
        return DB::connection()->transaction(function () use ($attempt, $attemptQuestion, $response, $clientSequence, $clientAnsweredAt, $timeSpentSeconds, $source): AttemptAnswer {
            $attempt = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $attemptQuestion = AttemptQuestion::query()->findOrFail($attemptQuestion->id);

            if ($attempt->status !== AttemptStatus::InProgress) {
                throw new RuntimeException('Answers can only be saved for an in-progress attempt.');
            }

            if ($attemptQuestion->attempt_id !== $attempt->id) {
                throw new RuntimeException('The question does not belong to this attempt.');
            }

            $answer = AttemptAnswer::query()
                ->where('attempt_id', $attempt->id)
                ->where('attempt_question_id', $attemptQuestion->id)
                ->lockForUpdate()
                ->first();

            if (! $answer) {
                $answer = AttemptAnswer::query()->create([
                    'attempt_id' => $attempt->id,
                    'attempt_question_id' => $attemptQuestion->id,
                    'grading_status' => GradingStatus::Ungraded,
                ]);
            }

            $answer->revisions()->create([
                'response' => $response,
                'client_sequence' => $clientSequence,
                'client_answered_at' => $clientAnsweredAt,
                'received_at' => now(),
                'source' => $source,
            ]);

            if ($clientSequence < $answer->client_sequence) {
                return $answer->fresh();
            }

            $answer->forceFill([
                'response' => $response,
                'client_sequence' => $clientSequence,
                'client_answered_at' => $clientAnsweredAt,
                'answered_at' => now(),
                'time_spent_seconds' => max(0, $timeSpentSeconds),
                'grading_status' => GradingStatus::Ungraded,
                'score' => null,
                'is_correct' => null,
                'feedback' => null,
                'graded_by' => null,
                'graded_at' => null,
            ])->save();

            $attempt->forceFill(['last_seen_at' => now()])->save();

            return $answer->fresh();
        });
    }
}
