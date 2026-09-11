<?php

namespace App\Services\Invigilation;

use App\Models\Attempt;
use App\Models\Exam;

class InvigilationDashboard
{
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
                'score' => $attempt->score,
                'percentage' => $attempt->percentage,
            ])->values()->all(),
        ];
    }
}
