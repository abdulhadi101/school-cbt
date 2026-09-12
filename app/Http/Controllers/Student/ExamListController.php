<?php

namespace App\Http\Controllers\Student;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Exams\ExamEligibility;
use Inertia\Inertia;
use Inertia\Response;

class ExamListController extends Controller
{
    public function __invoke(ExamEligibility $eligibility): Response
    {
        $student = request()->user()->student;

        if (! $student) {
            abort(403, 'No student profile.');
        }

        $exams = Exam::query()
            ->with(['subject', 'term.academicSession', 'audiences', 'accommodations'])
            ->where('status', ExamStatus::Published)
            ->latest()
            ->get()
            ->map(function (Exam $exam) use ($student, $eligibility): array {
                $reason = $eligibility->reason($exam, $student);

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject?->name,
                    'term' => $exam->term ? $exam->term->academicSession?->name.' - '.$exam->term->name : null,
                    'exam_type' => $exam->exam_type,
                    'duration_minutes' => $exam->duration_minutes + $eligibility->extraMinutes($exam, $student),
                    'opens_at' => $exam->opens_at?->toDayDateTimeString(),
                    'closes_at' => $exam->closes_at?->toDayDateTimeString(),
                    'available' => $reason === null,
                    'unavailable_reason' => $reason,
                ];
            })
            ->filter(fn (array $exam): bool => $exam['available'] || ! in_array($exam['unavailable_reason'], [
                'This exam is not assigned to your class or section.',
            ], true))
            ->values();

        return Inertia::render('Student/Exams/Index', [
            'exams' => $exams,
        ]);
    }
}
