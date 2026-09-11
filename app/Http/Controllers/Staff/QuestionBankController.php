<?php

namespace App\Http\Controllers\Staff;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\QuestionTag;
use App\Models\QuestionVersion;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = QuestionBankEntry::query()
            ->with(['subject', 'classLevel', 'category', 'tags', 'latestVersion'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (QuestionBankEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'status' => $entry->status,
                'subject' => $entry->subject?->name,
                'class_level' => $entry->classLevel?->name,
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

        return Inertia::render('Staff/Questions/Index', [
            'entries' => $entries,
            'filters' => ['status' => $request->string('status')->toString()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Staff/Questions/Form', [
            'entry' => null,
            'lookups' => $this->lookups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $entry = DB::transaction(function () use ($data, $request): QuestionBankEntry {
            $entry = QuestionBankEntry::query()->create([
                'category_id' => $data['category_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'class_level_id' => $data['class_level_id'] ?? null,
                'created_by' => $request->user()?->id,
                'status' => 'draft',
                'title' => $data['title'],
            ]);

            $this->syncTags($entry, $data['tags'] ?? '');
            $this->createVersion($entry, $data, $request->user()?->id, 1);

            return $entry;
        });

        return redirect()->route('staff.questions.edit', $entry)->with('status', 'Question created.');
    }

    public function edit(QuestionBankEntry $question): Response
    {
        $question->load(['tags', 'latestVersion.options']);
        $version = $question->latestVersion;

        return Inertia::render('Staff/Questions/Form', [
            'entry' => [
                'id' => $question->id,
                'status' => $question->status,
                'title' => $question->title,
                'subject_id' => $question->subject_id,
                'class_level_id' => $question->class_level_id,
                'category_id' => $question->category_id,
                'tags' => $question->tags->pluck('name')->implode(', '),
                'version' => $version ? [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'type' => $version->type->value,
                    'difficulty' => $version->difficulty,
                    'question_text' => $version->question_text,
                    'default_marks' => $version->default_marks,
                    'negative_marks' => $version->negative_marks,
                    'grading_rules' => $version->grading_rules,
                    'general_feedback' => $version->general_feedback,
                    'explanation' => $version->explanation,
                    'options' => $version->options->sortBy('position')->values()->map(fn (QuestionOption $option): array => [
                        'option_text' => $option->option_text,
                        'fraction' => $option->fraction,
                        'feedback' => $option->feedback,
                    ]),
                ] : null,
            ],
            'lookups' => $this->lookups(),
        ]);
    }

    public function update(Request $request, QuestionBankEntry $question): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($question, $data, $request): void {
            $question->update([
                'category_id' => $data['category_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'class_level_id' => $data['class_level_id'] ?? null,
                'status' => 'draft',
                'title' => $data['title'],
            ]);

            $this->syncTags($question, $data['tags'] ?? '');

            $latest = $question->versions()->latest('version_number')->first();
            $versionNumber = ((int) ($latest?->version_number ?? 0)) + ($latest?->ready_at ? 1 : 0);

            if ($latest && ! $latest->ready_at) {
                $latest->options()->delete();
                $latest->update($this->versionAttributes($question, $data, $request->user()?->id, $latest->version_number));
                $this->createOptions($latest, $data);

                return;
            }

            $this->createVersion($question, $data, $request->user()?->id, max(1, $versionNumber));
        });

        return back()->with('status', 'Question saved.');
    }

    public function markReady(QuestionBankEntry $question): RedirectResponse
    {
        $question->load('latestVersion.options');
        $version = $question->latestVersion;

        if (! $version) {
            return back()->withErrors(['question' => 'Question has no version to review.']);
        }

        if ($this->requiresOptions($version->type->value) && $version->options->count() < 2) {
            return back()->withErrors(['question' => 'Choice questions need at least two options before review.']);
        }

        $version->update(['ready_at' => now()]);
        $question->update(['status' => 'ready']);

        return back()->with('status', 'Question marked ready.');
    }

    public function retire(QuestionBankEntry $question): RedirectResponse
    {
        $question->update(['status' => 'retired']);

        return back()->with('status', 'Question retired.');
    }

    private function validated(Request $request): array
    {
        if (is_string($request->input('grading_rules'))) {
            $decoded = json_decode($request->input('grading_rules') ?: '{}', true);
            $request->merge(['grading_rules' => is_array($decoded) ? $decoded : []]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'class_level_id' => ['nullable', 'integer', Rule::exists('class_levels', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('question_categories', 'id')],
            'tags' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::enum(QuestionType::class)],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'question_text' => ['required', 'string'],
            'default_marks' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'negative_marks' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'grading_rules' => ['nullable', 'array'],
            'general_feedback' => ['nullable', 'string'],
            'explanation' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'options.*.option_text' => ['required_with:options', 'string'],
            'options.*.fraction' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'options.*.feedback' => ['nullable', 'string'],
        ]);

        if ($this->requiresOptions($data['type']) && count($data['options'] ?? []) < 2) {
            abort(redirect()->back()->withErrors(['options' => 'Choice questions require at least two options.'])->withInput());
        }

        return $data;
    }

    private function createVersion(QuestionBankEntry $entry, array $data, ?int $userId, int $versionNumber): QuestionVersion
    {
        $version = QuestionVersion::query()->create($this->versionAttributes($entry, $data, $userId, $versionNumber));
        $this->createOptions($version, $data);

        return $version;
    }

    private function versionAttributes(QuestionBankEntry $entry, array $data, ?int $userId, int $versionNumber): array
    {
        return [
            'question_bank_entry_id' => $entry->id,
            'version_number' => $versionNumber,
            'type' => $data['type'],
            'difficulty' => $data['difficulty'] ?? null,
            'question_text' => $data['question_text'],
            'default_marks' => $data['default_marks'],
            'negative_marks' => $data['negative_marks'] ?? 0,
            'grading_rules' => $data['grading_rules'] ?? [],
            'general_feedback' => $data['general_feedback'] ?? null,
            'explanation' => $data['explanation'] ?? null,
            'content_hash' => $this->contentHash($data),
            'created_by' => $userId,
            'ready_at' => null,
        ];
    }

    private function createOptions(QuestionVersion $version, array $data): void
    {
        foreach (array_values($data['options'] ?? []) as $index => $option) {
            QuestionOption::query()->create([
                'question_version_id' => $version->id,
                'position' => $index + 1,
                'option_text' => $option['option_text'],
                'fraction' => $option['fraction'] ?? 0,
                'feedback' => $option['feedback'] ?? null,
            ]);
        }
    }

    private function syncTags(QuestionBankEntry $entry, string $tags): void
    {
        $tagIds = collect(explode(',', $tags))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(fn (string $tag): string => Str::lower($tag))
            ->map(fn (string $tag): int => QuestionTag::query()->firstOrCreate(['name' => $tag])->id)
            ->all();

        $entry->tags()->sync($tagIds);
    }

    private function contentHash(array $data): string
    {
        return hash('sha256', json_encode(Arr::only($data, [
            'type',
            'difficulty',
            'question_text',
            'default_marks',
            'negative_marks',
            'grading_rules',
            'general_feedback',
            'explanation',
            'options',
        ]), JSON_THROW_ON_ERROR));
    }

    private function requiresOptions(string $type): bool
    {
        return in_array($type, [QuestionType::SingleChoice->value, QuestionType::MultipleChoice->value, QuestionType::TrueFalse->value], true);
    }

    private function lookups(): array
    {
        return [
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'class_levels' => ClassLevel::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'categories' => QuestionCategory::query()->orderBy('name')->get(['id', 'name', 'subject_id', 'class_level_id']),
            'types' => collect(QuestionType::cases())->map(fn (QuestionType $type): array => ['value' => $type->value, 'label' => Str::headline($type->value)]),
            'difficulties' => ['easy', 'medium', 'hard'],
        ];
    }
}
