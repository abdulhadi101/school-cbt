<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\QuestionImport\QuestionImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SubjectClassQuestionController extends Controller
{
    public function index(Request $request, Subject $subject, ClassLevel $classLevel): Response
    {
        $this->authorizeContext($request, $subject, $classLevel);

        $entries = QuestionBankEntry::query()
            ->with(['category', 'tags', 'latestVersion'])
            ->where('subject_id', $subject->id)
            ->where('class_level_id', $classLevel->id)
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (QuestionBankEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'status' => $entry->status,
                'category' => $entry->category?->name,
                'tags' => $entry->tags->pluck('name')->values(),
                'latest_version' => $entry->latestVersion ? [
                    'id' => $entry->latestVersion->id,
                    'version_number' => $entry->latestVersion->version_number,
                    'type' => $entry->latestVersion->type->value,
                    'difficulty' => $entry->latestVersion->difficulty,
                    'default_marks' => $entry->latestVersion->default_marks,
                    'question_text' => Str::limit(strip_tags($entry->latestVersion->question_text), 140),
                ] : null,
            ]);

        return Inertia::render('Staff/SubjectQuestions/Index', [
            'context' => $this->context($subject, $classLevel),
            'entries' => $entries,
        ]);
    }

    public function importForm(Request $request, Subject $subject, ClassLevel $classLevel): Response
    {
        $this->authorizeContext($request, $subject, $classLevel);

        return Inertia::render('Staff/SubjectQuestions/Import', [
            'context' => $this->context($subject, $classLevel),
            'preview' => null,
            'defaults' => ['default_marks' => '1', 'difficulty' => 'medium', 'tags' => '', 'format' => 'aiken'],
        ]);
    }

    public function preview(Request $request, Subject $subject, ClassLevel $classLevel): Response
    {
        $this->authorizeContext($request, $subject, $classLevel);
        $content = $this->content($request);
        $format = (string) $request->input('format', 'aiken');

        $result = QuestionImportService::parse($format, $content);
        $limited = array_slice($result['questions'], 0, 200);

        return Inertia::render('Staff/SubjectQuestions/Import', [
            'context' => $this->context($subject, $classLevel),
            'preview' => [
                'format' => $format,
                'questions' => array_map(fn ($q): array => [
                    'title' => $q->title,
                    'question_text' => $q->questionText,
                    'type' => $q->type,
                    'options' => $q->options,
                    'general_feedback' => $q->generalFeedback,
                ], $limited),
                'errors' => $result['errors'],
                'truncated' => count($result['questions']) > 200,
            ],
            'defaults' => [
                'default_marks' => (string) $request->input('default_marks', '1'),
                'difficulty' => $request->input('difficulty'),
                'tags' => (string) $request->input('tags', ''),
                'format' => $format,
                'content' => Str::limit($content, 20000),
            ],
        ]);
    }

    public function store(Request $request, Subject $subject, ClassLevel $classLevel): RedirectResponse
    {
        $this->authorizeContext($request, $subject, $classLevel);

        $request->validate([
            'format' => ['required', 'in:aiken,gift,xml'],
            'default_marks' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $content = $this->content($request);
        $result = QuestionImportService::parse((string) $request->input('format'), $content);
        $valid = array_slice($result['questions'], 0, 200);

        if ($valid === []) {
            return back()->withErrors(['content' => 'No valid questions found. Fix the errors and try again.'])->withInput();
        }

        $count = QuestionImportService::store(
            $request->user(),
            $subject,
            $classLevel,
            $valid,
            [
                'default_marks' => (float) $request->input('default_marks', 1),
                'difficulty' => $request->input('difficulty'),
                'tags' => (string) $request->input('tags', ''),
            ]
        );

        $skipped = count($result['errors']);

        return redirect()
            ->route('staff.subject-questions.index', [$subject, $classLevel])
            ->with('status', "Imported {$count} question(s).".($skipped > 0 ? " Skipped {$skipped} block(s)." : ''));
    }

    private function content(Request $request): string
    {
        $request->validate([
            'format' => ['nullable', 'in:aiken,gift,xml'],
            'content' => ['nullable', 'string', 'max:500000'],
            'file' => ['nullable', 'file', 'max:2048', 'mimes:txt,text,gift,xml'],
        ]);

        if ($request->hasFile('file')) {
            return (string) $request->file('file')->get();
        }

        return (string) $request->input('content', '');
    }

    private function authorizeContext(Request $request, Subject $subject, ClassLevel $classLevel): void
    {
        $user = $request->user();

        if ($user->hasPermission('academic.manage')) {
            return;
        }

        $profile = StaffProfile::query()->where('user_id', $user->id)->first();

        if (! $profile) {
            abort(403, 'No teaching assignment.');
        }

        $allowed = TeachingAssignment::query()
            ->where('staff_profile_id', $profile->id)
            ->where('subject_id', $subject->id)
            ->where(function (Builder $query) use ($classLevel): void {
                $query->where('class_level_id', $classLevel->id)
                    ->orWhereHas('section', fn (Builder $section): Builder => $section->where('class_level_id', $classLevel->id));
            })
            ->exists();

        if (! $allowed) {
            abort(403, 'You are not assigned to this subject and class.');
        }
    }

    private function context(Subject $subject, ClassLevel $classLevel): array
    {
        $category = QuestionCategory::query()
            ->where('subject_id', $subject->id)
            ->where('class_level_id', $classLevel->id)
            ->first();

        return [
            'subject' => ['id' => $subject->id, 'name' => $subject->name, 'code' => $subject->code],
            'class_level' => ['id' => $classLevel->id, 'name' => $classLevel->name],
            'category' => $category ? ['id' => $category->id, 'name' => $category->name] : null,
        ];
    }
}
