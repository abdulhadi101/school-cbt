<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isStaff = $user->hasPermission('exams.view');

        if ($isStaff) {
            return $this->staffDashboard();
        }

        return $this->studentDashboard($user);
    }

    private function staffDashboard(): Response
    {
        $stats = Cache::remember('dashboard:staff:stats', 60, fn () => [
            'published_exams' => Exam::query()->where('status', ExamStatus::Published)->count(),
            'total_attempts' => Attempt::query()->count(),
            'pending_grading' => Attempt::query()->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Grading])->count(),
            'released_results' => Attempt::query()->where('status', AttemptStatus::Released)->count(),
        ]);

        $recentAttempts = Attempt::query()
            ->with('exam:id,title', 'student:id,first_name,last_name,admission_number')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Attempt $attempt) => [
                'id' => $attempt->id,
                'exam_title' => $attempt->exam?->title ?? 'Deleted exam',
                'student_name' => trim(($attempt->student?->first_name ?? '').' '.($attempt->student?->last_name ?? '')),
                'admission_number' => $attempt->student?->admission_number,
                'status' => $attempt->status->value,
                'score' => $attempt->score,
                'submitted_at' => $attempt->submitted_at?->toISOString(),
            ]);

        $examsNeedingGrading = Exam::query()
            ->whereHas('attempts', fn ($q) => $q->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Grading]))
            ->withCount(['attempts as pending_count' => fn ($q) => $q->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Grading])])
            ->withCount('attempts as total_count')
            ->orderByDesc('pending_count')
            ->limit(5)
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'pending_count' => $exam->pending_count,
                'total_count' => $exam->total_count,
            ]);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentAttempts' => $recentAttempts,
            'examsNeedingGrading' => $examsNeedingGrading,
        ]);
    }

    private function studentDashboard($user): Response
    {
        $student = $user->student;

        $stats = $student
            ? Cache::remember("dashboard:student:{$student->id}:stats", 60, fn () => [
                'total_attempts' => Attempt::where('student_id', $student->id)->count(),
                'released_results' => Attempt::where('student_id', $student->id)->where('status', AttemptStatus::Released)->count(),
                'average_score' => round((float) Attempt::where('student_id', $student->id)->where('status', AttemptStatus::Released)->avg('percentage'), 1),
            ])
            : [
                'total_attempts' => 0,
                'released_results' => 0,
                'average_score' => 0,
            ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentAttempts' => [],
            'examsNeedingGrading' => [],
        ]);
    }
}
