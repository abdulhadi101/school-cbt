<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamDraftSlot;
use App\Models\QuestionVersion;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExamDraftController extends Controller
{
    public function index(Request $request): Response
    {
        $exams = Exam::query()
            ->with(['subject', 'term.academicSession'])
            ->withCount('draftSlots')
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Exam $exam): array => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'exam_type' => $exam->exam_type,
                'subject' => $exam->subject?->name,
                'term' => $exam->term ? $exam->term->academicSession?->name.' - '.$exam->term->name : null,
                'duration_minutes' => $exam->duration_minutes,
                'total_marks' => $exam->total_marks,
                'draft_slots_count' => $exam->draft_slots_count,
            ]);

        return Inertia::render('Staff/Exams/Index', [
            'exams' => $exams,
            'filters' => ['status' => $request->string('status')->toString()],
            'actions' => $this->actions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Staff/Exams/Form', [
            'exam' => null,
            'lookups' => $this->lookups(),
            'actions' => $this->actions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $exam = DB::transaction(function () use ($data, $request): Exam {
            $exam = Exam::query()->create($this->examAttributes($data, $request));
            $this->syncSlots($exam, $data['slots'] ?? []);

            return $exam;
        });

        return redirect()->route('staff.exams.edit', $exam)->with('status', 'Exam draft created.');
    }

    public function edit(Exam $exam): Response
    {
        $exam->load(['draftSlots.questionVersion.entry.subject', 'draftSlots.questionVersion.entry.classLevel']);

        return Inertia::render('Staff/Exams/Form', [
            'exam' => $this->examPayload($exam),
            'lookups' => $this->lookups(),
            'actions' => $this->actions($exam),
        ]);
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        if (! in_array($exam->status, [ExamStatus::Draft, ExamStatus::Rejected], true)) {
            return back()->withErrors(['exam' => 'Only draft or rejected exams can be edited.']);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($exam, $data, $request): void {
            $exam->update($this->examAttributes($data, $request, $exam));
            $this->syncSlots($exam, $data['slots'] ?? []);
        });

        return back()->with('status', 'Exam draft saved.');
    }

    public function submit(Exam $exam, Request $request): RedirectResponse
    {
        if (! in_array($exam->status, [ExamStatus::Draft, ExamStatus::Rejected], true)) {
            return back()->withErrors(['exam' => 'Only draft or rejected exams can be submitted.']);
        }

        $error = $this->submissionError($exam);

        if ($error) {
            return back()->withErrors(['exam' => $error]);
        }

        $exam->update([
            'status' => ExamStatus::Submitted,
            'submitted_by' => $request->user()?->id,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Exam submitted for approval.');
    }

    public function approve(Exam $exam, Request $request): RedirectResponse
    {
        if ($exam->status !== ExamStatus::Submitted) {
            return back()->withErrors(['exam' => 'Only submitted exams can be approved.']);
        }

        $error = $this->submissionError($exam);

        if ($error) {
            return back()->withErrors(['exam' => $error]);
        }

        $exam->update([
            'status' => ExamStatus::Approved,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'review_notes' => $request->string('review_notes')->toString() ?: null,
        ]);

        return back()->with('status', 'Exam approved.');
    }

    public function publish(Exam $exam, ExamRevisionPublisher $publisher): RedirectResponse
    {
        if ($exam->status !== ExamStatus::Approved) {
            return back()->withErrors(['exam' => 'Only approved exams can be published.']);
        }

        try {
            $publisher->publish($exam, request()->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['exam' => $e->getMessage()]);
        }

        return back()->with('status', 'Exam published.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'term_id' => ['nullable', 'integer', Rule::exists('terms', 'id')],
            'exam_type' => ['required', Rule::in(['ca', 'exam', 'practice'])],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'total_marks' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'pass_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'score_release_policy' => ['required', Rule::in(['immediate', 'after_close', 'manual', 'scheduled'])],
            'score_release_at' => ['nullable', 'date', Rule::requiredIf($request->input('score_release_policy') === 'scheduled')],
            'show_responses' => ['boolean'],
            'show_correct_answers' => ['boolean'],
            'show_feedback' => ['boolean'],
            'slots' => ['nullable', 'array'],
            'slots.*.question_version_id' => ['required_with:slots', 'integer', Rule::exists('question_versions', 'id')],
            'slots.*.marks_per_question' => ['required_with:slots', 'numeric', 'min:0.01', 'max:999999'],
        ]);
    }

    private function examAttributes(array $data, Request $request, ?Exam $exam = null): array
    {
        return [
            'subject_id' => $data['subject_id'],
            'term_id' => $data['term_id'] ?? null,
            'created_by' => $exam?->created_by ?? $request->user()?->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'exam_type' => $data['exam_type'],
            'status' => ExamStatus::Draft,
            'duration_minutes' => $data['duration_minutes'],
            'total_marks' => $data['total_marks'],
            'pass_percentage' => $data['pass_percentage'],
            'max_attempts' => $data['max_attempts'],
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
            'shuffle_options' => (bool) ($data['shuffle_options'] ?? false),
            'score_release_policy' => $data['score_release_policy'],
            'score_release_at' => $data['score_release_at'] ?? null,
            'show_responses' => (bool) ($data['show_responses'] ?? false),
            'show_correct_answers' => (bool) ($data['show_correct_answers'] ?? false),
            'show_feedback' => (bool) ($data['show_feedback'] ?? false),
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'review_notes' => null,
        ];
    }

    private function syncSlots(Exam $exam, array $slots): void
    {
        $exam->draftSlots()->delete();

        foreach (array_values($slots) as $index => $slot) {
            $question = $this->readyQuestion((int) $slot['question_version_id'], (int) $exam->subject_id);

            if (! $question) {
                abort(redirect()->back()->withErrors([
                    'slots' => 'Draft slots can only use ready, non-retired question versions for the selected subject.',
                ])->withInput());
            }

            ExamDraftSlot::query()->create([
                'exam_id' => $exam->id,
                'slot_type' => 'fixed_question',
                'question_version_id' => $question->id,
                'question_count' => 1,
                'marks_per_question' => $slot['marks_per_question'],
                'position' => $index + 1,
            ]);
        }
    }

    private function readyQuestion(int $questionVersionId, ?int $subjectId = null): ?QuestionVersion
    {
        return QuestionVersion::query()
            ->whereKey($questionVersionId)
            ->whereNotNull('ready_at')
            ->whereHas('entry', function ($query) use ($subjectId): void {
                $query->where('status', 'ready')
                    ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId));
            })
            ->first();
    }

    private function submissionError(Exam $exam): ?string
    {
        $exam->load('draftSlots.questionVersion.entry');

        if ($exam->draftSlots->isEmpty()) {
            return 'An exam must have at least one question before submission.';
        }

        foreach ($exam->draftSlots as $slot) {
            if (! $this->readyQuestion((int) $slot->question_version_id, (int) $exam->subject_id)) {
                return 'Draft slots can only use ready, non-retired question versions for the selected subject.';
            }
        }

        $computedTotal = $exam->draftSlots->sum(
            fn (ExamDraftSlot $slot): float => (float) $slot->marks_per_question * (int) $slot->question_count
        );

        if (round($computedTotal, 2) !== round((float) $exam->total_marks, 2)) {
            return 'Exam slot marks must equal the configured total marks.';
        }

        return null;
    }

    private function examPayload(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'description' => $exam->description,
            'instructions' => $exam->instructions,
            'subject_id' => $exam->subject_id,
            'term_id' => $exam->term_id,
            'exam_type' => $exam->exam_type,
            'status' => $exam->status->value,
            'duration_minutes' => $exam->duration_minutes,
            'total_marks' => $exam->total_marks,
            'pass_percentage' => $exam->pass_percentage,
            'max_attempts' => $exam->max_attempts,
            'opens_at' => $this->dateTimeValue($exam->opens_at),
            'closes_at' => $this->dateTimeValue($exam->closes_at),
            'shuffle_questions' => $exam->shuffle_questions,
            'shuffle_options' => $exam->shuffle_options,
            'score_release_policy' => $exam->score_release_policy,
            'score_release_at' => $this->dateTimeValue($exam->score_release_at),
            'show_responses' => $exam->show_responses,
            'show_correct_answers' => $exam->show_correct_answers,
            'show_feedback' => $exam->show_feedback,
            'review_notes' => $exam->review_notes,
            'slots' => $exam->draftSlots->map(fn (ExamDraftSlot $slot): array => [
                'question_version_id' => $slot->question_version_id,
                'marks_per_question' => $slot->marks_per_question,
                'question_label' => $slot->questionVersion?->entry?->title,
            ])->values(),
        ];
    }

    private function lookups(): array
    {
        return [
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'terms' => Term::query()
                ->with('academicSession:id,name')
                ->orderByDesc('is_current')
                ->orderBy('name')
                ->get(['id', 'academic_session_id', 'name'])
                ->map(fn (Term $term): array => [
                    'id' => $term->id,
                    'name' => $term->academicSession?->name.' - '.$term->name,
                ]),
            'ready_questions' => QuestionVersion::query()
                ->with(['entry.subject', 'entry.classLevel'])
                ->whereNotNull('ready_at')
                ->whereHas('entry', fn ($query) => $query->where('status', 'ready'))
                ->latest('ready_at')
                ->get()
                ->map(fn (QuestionVersion $question): array => [
                    'id' => $question->id,
                    'label' => Str::limit(strip_tags($question->entry?->title ?: $question->question_text), 90),
                    'subject_id' => $question->entry?->subject_id,
                    'subject' => $question->entry?->subject?->name,
                    'class_level' => $question->entry?->classLevel?->name,
                    'type' => $question->type->value,
                    'difficulty' => $question->difficulty,
                    'default_marks' => $question->default_marks,
                ]),
            'exam_types' => ['ca', 'exam', 'practice'],
            'release_policies' => ['manual', 'immediate', 'after_close', 'scheduled'],
        ];
    }

    private function actions(?Exam $exam = null): array
    {
        $user = request()->user();

        return [
            'can_create' => (bool) $user?->hasPermission('exams.create'),
            'can_edit' => $exam
                ? (bool) $user?->hasPermission('exams.update') && in_array($exam->status, [ExamStatus::Draft, ExamStatus::Rejected], true)
                : (bool) $user?->hasPermission('exams.create'),
            'can_submit' => (bool) $user?->hasPermission('exams.submit') && $exam && in_array($exam->status, [ExamStatus::Draft, ExamStatus::Rejected], true),
            'can_approve' => (bool) $user?->hasPermission('exams.approve') && $exam?->status === ExamStatus::Submitted,
            'can_publish' => (bool) $user?->hasPermission('exams.publish') && $exam?->status === ExamStatus::Approved,
        ];
    }

    private function dateTimeValue(mixed $value): ?string
    {
        return $value?->format('Y-m-d\TH:i');
    }
}
