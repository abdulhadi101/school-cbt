<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttemptActionController extends Controller
{
    public function extend(Request $request, Attempt $attempt): RedirectResponse
    {
        $data = $request->validate([
            'extra_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $attempt = DB::transaction(function () use ($attempt, $data, $request): Attempt {
                $locked = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);

                $status = $locked->getAttribute('status');

                if (! $status instanceof AttemptStatus || $status !== AttemptStatus::InProgress) {
                    throw new RuntimeException('Only in-progress attempts can be extended.');
                }

                $deadline = $locked->getAttribute('deadline_at');
                $base = $deadline instanceof Carbon && $deadline->isFuture() ? $deadline : now();
                $locked->forceFill(['deadline_at' => $base->copy()->addMinutes($data['extra_minutes'])])->save();

                $locked->events()->create([
                    'user_id' => $request->user()?->id,
                    'event_type' => 'attempt_extended',
                    'metadata' => ['extra_minutes' => $data['extra_minutes'], 'reason' => $data['reason']],
                    'occurred_at' => now(),
                ]);

                return $locked;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['attempt' => $e->getMessage()]);
        }

        AuditLogger::log(
            action: 'attempt.extended',
            model: $attempt,
            newValues: ['extra_minutes' => $data['extra_minutes'], 'deadline_at' => $attempt->deadline_at],
            metadata: ['reason' => $data['reason']],
        );

        return back()->with('status', "Extended by {$data['extra_minutes']} minute(s).");
    }

    public function reopen(Request $request, Attempt $attempt): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $attempt = DB::transaction(function () use ($attempt, $data, $request): Attempt {
                $locked = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);

                $status = $locked->getAttribute('status');

                if (! $status instanceof AttemptStatus || ! in_array($status, [AttemptStatus::Submitted, AttemptStatus::Expired, AttemptStatus::Grading, AttemptStatus::Graded], true)) {
                    throw new RuntimeException('Only submitted or expired attempts can be reopened.');
                }

                $previous = $status->value;
                $locked->forceFill([
                    'status' => AttemptStatus::InProgress,
                    'submitted_at' => null,
                    'graded_at' => null,
                    'score' => null,
                    'percentage' => null,
                    'deadline_at' => now()->addMinutes((int) Exam::query()->whereKey($locked->exam_id)->value('duration_minutes')),
                    'last_seen_at' => now(),
                ])->save();

                $locked->events()->create([
                    'user_id' => $request->user()?->id,
                    'event_type' => 'attempt_reopened',
                    'metadata' => ['previous_status' => $previous, 'reason' => $data['reason']],
                    'occurred_at' => now(),
                ]);

                return $locked;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['attempt' => $e->getMessage()]);
        }

        AuditLogger::log(
            action: 'attempt.reopened',
            model: $attempt,
            newValues: ['status' => AttemptStatus::InProgress->value],
            metadata: ['reason' => $data['reason']],
        );

        return back()->with('status', 'Attempt reopened. The student can continue now.');
    }

    public function invalidate(Request $request, Attempt $attempt): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $attempt = DB::transaction(function () use ($attempt, $data, $request): Attempt {
                $locked = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);

                $status = $locked->getAttribute('status');

                if (! $status instanceof AttemptStatus || ! in_array($status, [AttemptStatus::InProgress, AttemptStatus::Submitted, AttemptStatus::Expired, AttemptStatus::Grading, AttemptStatus::Graded], true)) {
                    throw new RuntimeException('This attempt cannot be invalidated.');
                }

                $locked->forceFill([
                    'status' => AttemptStatus::Invalidated,
                    'invalidation_reason' => $data['reason'],
                    'invalidated_by' => $request->user()?->id,
                ])->save();

                $locked->events()->create([
                    'user_id' => $request->user()?->id,
                    'event_type' => 'attempt_invalidated',
                    'metadata' => ['reason' => $data['reason']],
                    'occurred_at' => now(),
                ]);

                return $locked;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['attempt' => $e->getMessage()]);
        }

        AuditLogger::log(
            action: 'attempt.invalidated',
            model: $attempt,
            newValues: ['status' => AttemptStatus::Invalidated->value],
            metadata: ['reason' => $data['reason']],
        );

        return back()->with('status', 'Attempt invalidated.');
    }
}
