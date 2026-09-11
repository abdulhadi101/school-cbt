<?php

namespace App\Services\Results;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Support\Carbon;

class ResultReleasePolicy
{
    public function isScoreReleased(Exam $exam, Attempt $attempt, ?Carbon $now = null): bool
    {
        $now ??= now();

        if (! in_array($attempt->status, [AttemptStatus::Graded, AttemptStatus::Released], true)) {
            return false;
        }

        return match ($exam->score_release_policy) {
            'immediate' => true,
            'after_close' => $exam->closes_at === null || $now->greaterThanOrEqualTo($exam->closes_at),
            'manual' => $attempt->status === AttemptStatus::Released,
            'scheduled' => $exam->score_release_at !== null && $now->greaterThanOrEqualTo($exam->score_release_at),
            default => false,
        };
    }

    public function canSeeResponses(Exam $exam, Attempt $attempt, ?Carbon $now = null): bool
    {
        return $exam->show_responses && $this->isScoreReleased($exam, $attempt, $now);
    }

    public function canSeeCorrectAnswers(Exam $exam, Attempt $attempt, ?Carbon $now = null): bool
    {
        return $exam->show_correct_answers && $this->isScoreReleased($exam, $attempt, $now);
    }

    public function canSeeFeedback(Exam $exam, Attempt $attempt, ?Carbon $now = null): bool
    {
        return $exam->show_feedback && $this->isScoreReleased($exam, $attempt, $now);
    }
}
