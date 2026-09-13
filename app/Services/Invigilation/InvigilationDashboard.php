<?php

namespace App\Services\Invigilation;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

class InvigilationDashboard
{
    public const IDLE_AFTER_SECONDS = 300;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function liveExams(): array
    {
        $exams = Exam::query()
            ->with(['subject:id,name,code'])
            ->where('status', ExamStatus::Published)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $open): void {
                    $open->whereNull('opens_at')->orWhere('opens_at', '<=', now());
                })->where(function (Builder $open): void {
                    $open->whereNull('closes_at')->orWhere('closes_at', '>=', now());
                })->orWhereHas('attempts', fn (Builder $attempts): Builder => $attempts->where('status', AttemptStatus::InProgress));
            })
            ->orderBy('closes_at')
            ->limit(50)
            ->get();

        $ids = $exams->pluck('id')->all();
        $totals = $this->attemptCounts($ids, null);
        $inProgress = $this->attemptCounts($ids, AttemptStatus::InProgress);
        $submitted = $this->attemptCounts($ids, AttemptStatus::Submitted);
        $expired = $this->attemptCounts($ids, AttemptStatus::Expired);

        return $exams->map(fn (Exam $exam): array => [
            'id' => $exam->id,
            'title' => $exam->title,
            'subject' => $exam->subject?->name,
            'subject_code' => $exam->subject?->code,
            'exam_type' => $exam->exam_type->value,
            'duration_minutes' => $exam->duration_minutes,
            'opens_at' => $exam->opens_at,
            'closes_at' => $exam->closes_at,
            'is_open' => $this->isOpen($exam),
            'total_attempts' => $totals[$exam->id] ?? 0,
            'in_progress_count' => $inProgress[$exam->id] ?? 0,
            'submitted_count' => $submitted[$exam->id] ?? 0,
            'expired_count' => $expired[$exam->id] ?? 0,
        ])->sortByDesc('in_progress_count')->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forExam(Exam $exam): array
    {
        $attempts = Attempt::query()
            ->with('student')
            ->where('exam_id', $exam->id)
            ->orderBy('status')
            ->orderBy('last_seen_at')
            ->get();

        return [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'opens_at' => $exam->opens_at,
                'closes_at' => $exam->closes_at,
            ],
            'summary' => [
                'total_attempts' => $attempts->count(),
                'in_progress' => $attempts->where('status.value', 'in_progress')->count(),
                'submitted' => $attempts->where('status.value', 'submitted')->count(),
                'expired' => $attempts->where('status.value', 'expired')->count(),
                'grading' => $attempts->where('status.value', 'grading')->count(),
                'graded' => $attempts->where('status.value', 'graded')->count(),
                'released' => $attempts->where('status.value', 'released')->count(),
            ],
            'attempts' => $attempts->map(fn (Attempt $attempt) => [
                'id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'student_name' => trim(($attempt->student->first_name ?? '').' '.($attempt->student->last_name ?? '')),
                'admission_number' => $attempt->student->admission_number ?? null,
                'status' => $attempt->status->value,
                'attempt_number' => $attempt->attempt_number,
                'started_at' => $attempt->started_at,
                'deadline_at' => $attempt->deadline_at,
                'submitted_at' => $attempt->submitted_at,
                'last_seen_at' => $attempt->last_seen_at,
                'remaining_seconds' => $attempt->deadline_at ? max(0, now()->diffInSeconds($attempt->deadline_at, false)) : null,
                'seconds_since_seen' => $attempt->last_seen_at ? max(0, now()->diffInSeconds($attempt->last_seen_at)) : null,
                'is_idle' => $this->isIdle($attempt),
                'can_extend' => $attempt->getAttribute('status') === AttemptStatus::InProgress,
                'can_reopen' => in_array($attempt->getAttribute('status'), [AttemptStatus::Submitted, AttemptStatus::Expired, AttemptStatus::Grading, AttemptStatus::Graded], true),
                'can_invalidate' => in_array($attempt->getAttribute('status'), [AttemptStatus::InProgress, AttemptStatus::Submitted, AttemptStatus::Expired, AttemptStatus::Grading, AttemptStatus::Graded], true),
                'score' => $attempt->score,
                'percentage' => $attempt->percentage,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function attemptCounts(array $examIds, ?AttemptStatus $status): array
    {
        if ($examIds === []) {
            return [];
        }

        $rows = Attempt::query()->toBase()
            ->selectRaw('exam_id, COUNT(*) as total')
            ->whereIn('exam_id', $examIds)
            ->when($status, fn (QueryBuilder $query): QueryBuilder => $query->where('status', $status?->value))
            ->groupBy('exam_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->exam_id] = (int) $row->total;
        }

        return $counts;
    }

    private function isIdle(Attempt $attempt): bool
    {
        if ($attempt->getAttribute('status') !== AttemptStatus::InProgress) {
            return false;
        }

        $seen = $attempt->getAttribute('last_seen_at') ?? $attempt->getAttribute('started_at');

        if (! $seen instanceof Carbon) {
            return true;
        }

        return $seen->diffInSeconds(now()) >= self::IDLE_AFTER_SECONDS;
    }

    private function isOpen(Exam $exam): bool
    {
        $now = now();

        if ($exam->opens_at && $now->lt($exam->opens_at)) {
            return false;
        }

        if ($exam->closes_at && $now->gt($exam->closes_at)) {
            return false;
        }

        return true;
    }
}
