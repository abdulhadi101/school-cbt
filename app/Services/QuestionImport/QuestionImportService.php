<?php

namespace App\Services\QuestionImport;

use App\Enums\QuestionType;
use App\Models\ClassLevel;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\QuestionTag;
use App\Models\QuestionVersion;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QuestionImportService
{
    /**
     * @param  array<int, ParsedQuestion>  $parsed
     * @param  array{default_marks: float, difficulty: ?string, tags: string}  $defaults
     */
    public static function store(User $user, Subject $subject, ClassLevel $classLevel, array $parsed, array $defaults): int
    {
        $category = QuestionCategory::query()->firstOrCreate(
            [
                'subject_id' => $subject->id,
                'class_level_id' => $classLevel->id,
                'slug' => Str::slug($subject->code.' '.$classLevel->name.' general'),
            ],
            [
                'parent_id' => null,
                'name' => $subject->name.' '.$classLevel->name.' General',
            ]
        );

        $tagIds = collect(explode(',', (string) ($defaults['tags'] ?? '')))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(fn (string $tag): string => Str::lower($tag))
            ->map(fn (string $tag): int => QuestionTag::query()->firstOrCreate(['name' => $tag])->id)
            ->all();

        return DB::transaction(function () use ($user, $subject, $classLevel, $category, $parsed, $defaults, $tagIds): int {
            $count = 0;

            foreach ($parsed as $item) {
                $type = QuestionType::from($item->type);

                if (in_array($type, [QuestionType::SingleChoice, QuestionType::MultipleChoice, QuestionType::TrueFalse], true)
                    && count($item->options) < 2) {
                    continue;
                }

                $entry = QuestionBankEntry::query()->create([
                    'category_id' => $category->id,
                    'subject_id' => $subject->id,
                    'class_level_id' => $classLevel->id,
                    'created_by' => $user->id,
                    'status' => 'draft',
                    'title' => $item->title ?? Str::limit(strip_tags($item->questionText), 120),
                ]);

                if ($tagIds !== []) {
                    $entry->tags()->sync($tagIds);
                }

                $attributes = [
                    'question_bank_entry_id' => $entry->id,
                    'version_number' => 1,
                    'type' => $type->value,
                    'difficulty' => $defaults['difficulty'] ?? null,
                    'question_text' => $item->questionText,
                    'default_marks' => $defaults['default_marks'] ?? 1,
                    'negative_marks' => 0,
                    'grading_rules' => $item->gradingRules,
                    'general_feedback' => $item->generalFeedback,
                    'explanation' => $item->explanation,
                    'created_by' => $user->id,
                    'ready_at' => null,
                ];
                $attributes['content_hash'] = hash('sha256', json_encode(Arr::only($attributes, [
                    'type', 'difficulty', 'question_text', 'default_marks', 'negative_marks',
                    'grading_rules', 'general_feedback', 'explanation',
                ]), JSON_THROW_ON_ERROR));

                $version = QuestionVersion::query()->create($attributes);

                foreach (array_values($item->options) as $position => $option) {
                    QuestionOption::query()->create([
                        'question_version_id' => $version->id,
                        'position' => $position + 1,
                        'option_text' => $option['option_text'],
                        'fraction' => $option['fraction'] ?? 0,
                        'feedback' => $option['feedback'] ?? null,
                    ]);
                }

                $count++;
            }

            return $count;
        });
    }

    /**
     * @return array{questions: array<int, ParsedQuestion>, errors: array<int, array{block: int, message: string}>, format: string}
     */
    public static function parse(string $format, string $content): array
    {
        $result = match ($format) {
            'gift' => GiftParser::parse($content),
            'xml' => MoodleXmlParser::parse($content),
            default => AikenParser::parse($content),
        };

        return [...$result, 'format' => $format];
    }
}
