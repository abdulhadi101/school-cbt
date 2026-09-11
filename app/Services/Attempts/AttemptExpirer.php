<?php

namespace App\Services\Attempts;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use Illuminate\Support\Facades\DB;

class AttemptExpirer
{
    public function __construct(private readonly AttemptSubmitter $submitter) {}

    public function expireDue(int $limit = 100): int
    {
        $ids = Attempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->where('deadline_at', '<=', now())
            ->orderBy('deadline_at')
            ->limit($limit)
            ->pluck('id');

        $expired = 0;

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $attempt = Attempt::query()->lockForUpdate()->find($id);

                if (! $attempt || $attempt->status !== AttemptStatus::InProgress) {
                    return;
                }

                $submitted = $this->submitter->submit($attempt->fresh());

                if ($submitted->status === AttemptStatus::Graded) {
                    $submitted->forceFill(['status' => AttemptStatus::Expired])->save();
                }

                $submitted->events()->create([
                    'user_id' => null,
                    'event_type' => 'attempt_expired',
                    'metadata' => [
                        'auto_submitted' => true,
                        'final_status' => $submitted->fresh()->status->value,
                    ],
                    'occurred_at' => now(),
                ]);

                $expired++;
            });
        }

        return $expired;
    }
}
