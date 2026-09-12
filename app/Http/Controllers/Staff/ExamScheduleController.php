<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAccommodation;
use App\Models\ExamAudience;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExamScheduleController extends Controller
{
    public function edit(Exam $exam): Response
    {
        $exam->load(['subject', 'audiences.classLevel', 'audiences.section', 'accommodations.student']);

        return Inertia::render('Staff/Exams/Schedule', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'subject' => $exam->subject?->name,
                'opens_at' => $this->dateTimeValue($exam->opens_at),
                'closes_at' => $this->dateTimeValue($exam->closes_at),
                'audiences' => $exam->audiences->map(fn (ExamAudience $audience): array => [
                    'class_level_id' => $audience->class_level_id,
                    'section_id' => $audience->section_id,
                ])->values(),
                'accommodations' => $exam->accommodations->map(fn (ExamAccommodation $accommodation): array => [
                    'student_id' => $accommodation->student_id,
                    'extra_minutes' => $accommodation->extra_minutes,
                    'opens_at' => $this->dateTimeValue($accommodation->opens_at),
                    'closes_at' => $this->dateTimeValue($accommodation->closes_at),
                    'extra_attempts' => $accommodation->extra_attempts,
                    'reason' => $accommodation->reason,
                ])->values(),
            ],
            'lookups' => $this->lookups(),
        ]);
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate([
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'audiences' => ['nullable', 'array'],
            'audiences.*.class_level_id' => ['required_with:audiences', 'integer', Rule::exists('class_levels', 'id')],
            'audiences.*.section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'accommodations' => ['nullable', 'array'],
            'accommodations.*.student_id' => ['required_with:accommodations', 'integer', Rule::exists('students', 'id')],
            'accommodations.*.extra_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'accommodations.*.opens_at' => ['nullable', 'date'],
            'accommodations.*.closes_at' => ['nullable', 'date', 'after:accommodations.*.opens_at'],
            'accommodations.*.extra_attempts' => ['nullable', 'integer', 'min:0', 'max:10'],
            'accommodations.*.reason' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($exam, $data, $request): void {
            $exam->update([
                'opens_at' => $data['opens_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
            ]);

            $exam->audiences()->delete();
            foreach (array_values($data['audiences'] ?? []) as $audience) {
                $sectionId = $audience['section_id'] ?? null;
                $section = $sectionId ? Section::query()->findOrFail($sectionId) : null;

                if ($section && (int) $section->class_level_id !== (int) $audience['class_level_id']) {
                    abort(redirect()->back()->withErrors(['audiences' => 'Selected section must belong to the selected class level.'])->withInput());
                }

                $exam->audiences()->create([
                    'class_level_id' => $audience['class_level_id'],
                    'section_id' => $sectionId,
                ]);
            }

            $exam->accommodations()->delete();
            foreach (array_values($data['accommodations'] ?? []) as $accommodation) {
                $exam->accommodations()->create([
                    'student_id' => $accommodation['student_id'],
                    'extra_minutes' => $accommodation['extra_minutes'] ?? 0,
                    'opens_at' => $accommodation['opens_at'] ?? null,
                    'closes_at' => $accommodation['closes_at'] ?? null,
                    'extra_attempts' => $accommodation['extra_attempts'] ?? 0,
                    'reason' => $accommodation['reason'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);
            }
        });

        return back()->with('status', 'Exam schedule saved.');
    }

    private function lookups(): array
    {
        return [
            'class_levels' => ClassLevel::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'sections' => Section::query()->orderBy('name')->get(['id', 'name', 'class_level_id']),
            'students' => Student::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'admission_number', 'first_name', 'last_name'])
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => trim($student->last_name.' '.$student->first_name).' ('.$student->admission_number.')',
                ]),
        ];
    }

    private function dateTimeValue(mixed $value): ?string
    {
        return $value?->format('Y-m-d\TH:i');
    }
}
