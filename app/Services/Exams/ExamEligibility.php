<?php

namespace App\Services\Exams;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamAccommodation;
use App\Models\Student;
use Illuminate\Support\Carbon;

class ExamEligibility
{
    public function reason(Exam $exam, Student $student, ?Carbon $at = null): ?string
    {
        $at ??= now();
        $exam->loadMissing(['audiences', 'accommodations']);

        if ($exam->status !== ExamStatus::Published) {
            return 'Only published exams can be attempted.';
        }

        $accommodation = $this->accommodation($exam, $student);

        if (! $accommodation && $exam->audiences->isNotEmpty() && ! $this->matchesAudience($exam, $student)) {
            return 'This exam is not assigned to your class or section.';
        }

        $opensAt = $accommodation?->opens_at ?: $exam->opens_at;
        $closesAt = $accommodation?->closes_at ?: $exam->closes_at;

        if ($opensAt && $at->lt($opensAt)) {
            return 'This exam has not opened yet.';
        }

        if ($closesAt && $at->gt($closesAt)) {
            return 'This exam has closed.';
        }

        $maxAttempts = (int) $exam->max_attempts + (int) ($accommodation?->extra_attempts ?? 0);

        if ($student->attempts()->where('exam_id', $exam->id)->count() >= $maxAttempts) {
            return 'The maximum number of attempts has been reached.';
        }

        return null;
    }

    public function isEligible(Exam $exam, Student $student, ?Carbon $at = null): bool
    {
        return $this->reason($exam, $student, $at) === null;
    }

    public function extraMinutes(Exam $exam, Student $student): int
    {
        return (int) ($this->accommodation($exam, $student)?->extra_minutes ?? 0);
    }

    private function accommodation(Exam $exam, Student $student): ?ExamAccommodation
    {
        $exam->loadMissing('accommodations');

        return $exam->accommodations->firstWhere('student_id', $student->id);
    }

    private function matchesAudience(Exam $exam, Student $student): bool
    {
        $enrollment = $student->enrollments()
            ->with('section')
            ->where('is_current', true)
            ->latest()
            ->first();

        if (! $enrollment || ! $enrollment->section) {
            return false;
        }

        $section = $enrollment->section;

        return $exam->audiences->contains(function ($audience) use ($section): bool {
            if ($audience->section_id) {
                return (int) $audience->section_id === (int) $section->id;
            }

            return (int) $audience->class_level_id === (int) $section->class_level_id;
        });
    }
}
