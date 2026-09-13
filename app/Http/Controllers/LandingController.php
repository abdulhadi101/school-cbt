<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\StudentStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $stats = Cache::remember('landing:stats', 300, fn (): array => [
            'students' => Student::query()->where('status', StudentStatus::Active)->count(),
            'subjects' => Subject::query()->count(),
            'published_exams' => Exam::query()->where('status', ExamStatus::Published)->count(),
            'completed_attempts' => Attempt::query()->whereIn('status', [
                AttemptStatus::Submitted,
                AttemptStatus::Grading,
                AttemptStatus::Graded,
                AttemptStatus::Released,
            ])->count(),
        ]);

        $upcoming = Exam::query()
            ->with(['subject:id,name', 'audiences.classLevel:id,name', 'audiences.section:id,name'])
            ->where('status', ExamStatus::Published)
            ->where(function ($query): void {
                $query->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            })
            ->orderBy('opens_at')
            ->limit(5)
            ->get()
            ->map(fn (Exam $exam): array => [
                'title' => $exam->title,
                'subject' => $exam->subject?->name,
                'exam_type' => $exam->exam_type->value,
                'opens_at' => $exam->opens_at,
                'closes_at' => $exam->closes_at,
                'is_open' => (! $exam->opens_at || now()->greaterThanOrEqualTo($exam->opens_at))
                    && (! $exam->closes_at || now()->lessThanOrEqualTo($exam->closes_at)),
                'audience' => $exam->audiences->map(fn ($audience): string => $audience->section
                    ? $audience->section->classLevel?->name.' '.$audience->section->name
                    : ($audience->classLevel?->name ?? ''))->filter()->unique()->values()->all(),
            ])->values()->all();

        return Inertia::render('Landing', [
            'stats' => $stats,
            'upcoming' => $upcoming,
            'canLogin' => Route::has('login'),
        ]);
    }
}
