<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Http\Request;
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
        $publishedExamCount = Exam::query()
            ->where('status', ExamStatus::Published)
            ->count();

        $totalAttempts = Attempt::query()->count();

        $pendingGrading = Attempt::query()
            ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Grading])
            ->count();

        $releasedCount = Attempt::query()
            ->where('status', AttemptStatus::Released)
            ->count();

        $recentAttempts = Attempt::query()
            ->with('exam:id,title,subject_id', 'student:id,first_name,last_name,admission_number')
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
            ->withCount(['attempts as total_count'])
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
            'stats' => [
                'published_exams' => $publishedExamCount,
                'total_attempts' => $totalAttempts,
                'pending_grading' => $pendingGrading,
                'released_results' => $releasedCount,
            ],
            'recentAttempts' => $recentAttempts,
            'examsNeedingGrading' => $examsNeedingGrading,
        ]);
    }

    private function studentDashboard($user): Response
    {
        $student = $user->student;

        $totalAttempts = $student
            ? Attempt::where('student_id', $student->id)->count()
            : 0;

        $releasedResults = $student
            ? Attempt::where('student_id', $student->id)
                ->where('status', AttemptStatus::Released)
                ->count()
            : 0;

        $averageScore = $student
            ? (float) Attempt::where('student_id', $student->id)
                ->where('status', AttemptStatus::Released)
                ->avg('percentage')
            : 0;

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_attempts' => $totalAttempts,
                'released_results' => $releasedResults,
                'average_score' => round($averageScore, 1),
            ],
            'recentAttempts' => [],
            'examsNeedingGrading' => [],
        ]);
    }
}
