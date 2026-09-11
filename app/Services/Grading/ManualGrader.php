<?php

namespace App\Services\Grading;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ManualGrader
{
    public function grade(
        AttemptAnswer $answer,
        User $grader,
        float $score,
        ?string $feedback = null,
        ?string $reason = null
    ): AttemptAnswer {
        return DB::transaction(function () use ($answer, $grader, $score, $feedback, $reason) {
            $answer = AttemptAnswer::query()
                ->with(['attemptQuestion', 'attempt.questions', 'attempt.answers'])
                ->lockForUpdate()
                ->findOrFail($answer->id);

            $attempt = $answer->attempt;
            $attemptQuestion = $answer->attemptQuestion;

            if (! in_array($attempt->status, [
                AttemptStatus::Grading,
                AttemptStatus::Submitted,
                AttemptStatus::Expired,
                AttemptStatus::Graded,
                AttemptStatus::Released,
            ], true)) {
                throw new RuntimeException('This attempt is not ready for manual grading.');
            }

            $maxMarks = (float) $attemptQuestion->marks;
            $score = round($score, 2);

            if ($score < 0 || $score > $maxMarks) {
                throw new RuntimeException('Score must be between 0 and the question marks.');
            }

            $previousScore = $answer->score !== null ? (float) $answer->score : null;

            $answer->manualGrades()->create([
                'grader_id' => $grader->id,
                'previous_score' => $previousScore,
                'score' => $score,
                'feedback' => $feedback,
                'reason' => $reason,
                'is_final' => true,
            ]);

            $answer->forceFill([
                'grading_status' => GradingStatus::ManuallyGraded,
                'score' => $score,
                'is_correct' => $score >= ($maxMarks - 0.001),
                'feedback' => $feedback,
                'graded_by' => $grader->id,
                'graded_at' => now(),
            ])->save();

            $attempt->events()->create([
                'user_id' => $grader->id,
                'event_type' => 'manual_grade',
                'metadata' => [
                    'attempt_answer_id' => $answer->id,
                    'attempt_question_id' => $attemptQuestion->id,
                    'previous_score' => $previousScore,
                    'score' => $score,
                ],
                'occurred_at' => now(),
            ]);

            $this->finalizeIfComplete($attempt->fresh(['questions', 'answers']));

            return $answer->fresh();
        });
    }

    private function finalizeIfComplete(Attempt $attempt): void
    {
        $attempt->loadMissing(['questions', 'answers']);

        $pending = $attempt->answers->contains(
            fn (AttemptAnswer $a) => in_array($a->grading_status, [
                GradingStatus::Ungraded,
                GradingStatus::NeedsGrading,
            ], true)
        );

        if ($pending) {
            if ($attempt->status === AttemptStatus::Submitted) {
                $attempt->forceFill(['status' => AttemptStatus::Grading])->save();
            }

            return;
        }

        $score = round($attempt->answers->sum(fn (AttemptAnswer $a) => (float) $a->score), 2);
        $maxScore = round($attempt->questions->sum(fn ($q) => (float) $q->marks), 2);

        $updates = [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0,
            'graded_at' => $attempt->graded_at ?: now(),
            'last_seen_at' => now(),
        ];

        if ($attempt->status !== AttemptStatus::Released) {
            $updates['status'] = AttemptStatus::Graded;
        }

        $attempt->forceFill($updates)->save();
    }
}
