<?php

namespace App\Services\Results;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttemptReleaser
{
    public function release(Attempt $attempt, User $actor): Attempt
    {
        return DB::transaction(function () use ($attempt, $actor) {
            $attempt = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== AttemptStatus::Graded) {
                throw new RuntimeException('Only fully graded attempts can be released.');
            }

            $attempt->forceFill([
                'status' => AttemptStatus::Released,
                'released_at' => now(),
            ])->save();

            $attempt->events()->create([
                'user_id' => $actor->id,
                'event_type' => 'result_released',
                'metadata' => ['score' => $attempt->score],
                'occurred_at' => now(),
            ]);

            return $attempt->fresh();
        });
    }

    public function releaseAllForExam(Exam $exam, User $actor): int
    {
        $ids = Attempt::query()
            ->where('exam_id', $exam->id)
            ->where('status', AttemptStatus::Graded)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->release(Attempt::query()->findOrFail($id), $actor);
        }

        return $ids->count();
    }
}
