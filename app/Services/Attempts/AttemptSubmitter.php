<?php

namespace App\Services\Attempts;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttemptSubmitter
{
    public function __construct(private readonly AnswerGrader $grader) {}

    public function submit(Attempt $attempt): Attempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = Attempt::query()
                ->with(['questions.answer', 'answers'])
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            if (in_array($attempt->status, [AttemptStatus::Graded, AttemptStatus::Grading, AttemptStatus::Submitted, AttemptStatus::Released], true)) {
                return $attempt;
            }

            if ($attempt->status !== AttemptStatus::InProgress) {
                throw new RuntimeException('Only in-progress attempts can be submitted.');
            }

            foreach ($attempt->questions as $attemptQuestion) {
                $answer = $attemptQuestion->answer;

                if (! $answer) {
                    $answer = AttemptAnswer::query()->create([
                        'attempt_id' => $attempt->id,
                        'attempt_question_id' => $attemptQuestion->id,
                        'response' => null,
                        'grading_status' => GradingStatus::Ungraded,
                    ]);
                }

                $this->grader->grade($answer->load('attemptQuestion'));
            }

            $attempt->load('answers', 'questions');

            $needsManualGrading = $attempt->answers->contains(
                fn (AttemptAnswer $answer) => $answer->grading_status === GradingStatus::NeedsGrading
            );
            $score = round($attempt->answers->sum(fn (AttemptAnswer $answer) => (float) $answer->score), 2);
            $maxScore = round($attempt->questions->sum(fn ($question) => (float) $question->marks), 2);

            $attempt->forceFill([
                'status' => $needsManualGrading ? AttemptStatus::Grading : AttemptStatus::Graded,
                'submitted_at' => now(),
                'graded_at' => $needsManualGrading ? null : now(),
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0,
                'last_seen_at' => now(),
            ])->save();

            $attempt->events()->create([
                'user_id' => $attempt->student->user_id,
                'event_type' => 'attempt_submitted',
                'metadata' => ['score' => $score, 'needs_manual_grading' => $needsManualGrading],
                'occurred_at' => now(),
            ]);

            return $attempt->fresh(['questions', 'answers']);
        });
    }
}
