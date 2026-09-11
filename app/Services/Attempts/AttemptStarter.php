<?php

namespace App\Services\Attempts;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamSlot;
use App\Models\QuestionVersion;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttemptStarter
{
    public function startOrResume(Exam $exam, Student $student): Attempt
    {
        return DB::transaction(function () use ($exam, $student) {
            $exam = Exam::query()
                ->with(['latestRevision.slots.questionVersion.options', 'latestRevision.slots.questionCategory'])
                ->lockForUpdate()
                ->findOrFail($exam->id);

            if ($exam->status !== ExamStatus::Published) {
                throw new RuntimeException('Only published exams can be attempted.');
            }

            $activeAttempt = Attempt::query()
                ->where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->where('status', AttemptStatus::InProgress)
                ->first();

            if ($activeAttempt) {
                $activeAttempt->forceFill(['last_seen_at' => now()])->save();
                $activeAttempt->events()->create([
                    'user_id' => $student->user_id,
                    'event_type' => 'attempt_resumed',
                    'metadata' => ['attempt_number' => $activeAttempt->attempt_number],
                    'occurred_at' => now(),
                ]);

                return $activeAttempt->load('questions');
            }

            $latestRevision = $exam->latestRevision;

            if (! $latestRevision) {
                throw new RuntimeException('The exam has no published revision.');
            }

            $attemptCount = Attempt::query()
                ->where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->count();

            if ($attemptCount >= $exam->max_attempts) {
                throw new RuntimeException('The maximum number of attempts has been reached.');
            }

            $seed = random_int(1, PHP_INT_MAX);

            $attempt = Attempt::query()->create([
                'exam_id' => $exam->id,
                'exam_revision_id' => $latestRevision->id,
                'student_id' => $student->id,
                'attempt_number' => $attemptCount + 1,
                'status' => AttemptStatus::InProgress,
                'started_at' => now(),
                'deadline_at' => now()->addMinutes($exam->duration_minutes),
                'max_score' => $latestRevision->total_marks,
                'seed' => $seed,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'last_seen_at' => now(),
            ]);

            $questions = $this->resolveQuestions($latestRevision->slots, $seed);

            if ($exam->shuffle_questions) {
                $questions = $questions->sortBy(fn (array $item) => crc32($seed.'-'.$item['question']->id))->values();
            }

            foreach ($questions as $index => $item) {
                /** @var QuestionVersion $question */
                $question = $item['question'];
                /** @var ExamSlot $slot */
                $slot = $item['slot'];
                $optionOrder = $this->optionOrder($question, $seed, (bool) $exam->shuffle_options);

                $attempt->questions()->create([
                    'exam_slot_id' => $slot->id,
                    'question_version_id' => $question->id,
                    'position' => $index + 1,
                    'marks' => $slot->marks_per_question,
                    'question_snapshot' => $this->questionSnapshot($question),
                    'option_order' => $optionOrder,
                    'requires_manual_grading' => $question->type === QuestionType::Essay,
                ]);
            }

            $attempt->events()->create([
                'user_id' => $student->user_id,
                'event_type' => 'attempt_started',
                'metadata' => [
                    'attempt_number' => $attempt->attempt_number,
                    'exam_revision_id' => $attempt->exam_revision_id,
                ],
                'occurred_at' => now(),
            ]);

            return $attempt->load('questions');
        });
    }

    private function resolveQuestions($slots, int $seed)
    {
        return $slots
            ->sortBy('position')
            ->flatMap(function (ExamSlot $slot) use ($seed) {
                if ($slot->slot_type === 'fixed_question') {
                    return [[
                        'slot' => $slot,
                        'question' => $slot->questionVersion,
                    ]];
                }

                return $this->randomPoolQuestions($slot, $seed)
                    ->map(fn (QuestionVersion $question) => [
                        'slot' => $slot,
                        'question' => $question,
                    ]);
            })
            ->values();
    }

    private function randomPoolQuestions(ExamSlot $slot, int $seed)
    {
        $questions = QuestionVersion::query()
            ->with('options')
            ->whereHas('entry', function (Builder $query) use ($slot) {
                $query->where('status', 'ready');

                if ($slot->question_category_id) {
                    $query->where('category_id', $slot->question_category_id);
                }
            })
            ->when($slot->difficulty, fn (Builder $query) => $query->where('difficulty', $slot->difficulty))
            ->get()
            ->sortBy(fn (QuestionVersion $question) => crc32($seed.'-'.$slot->id.'-'.$question->id))
            ->take($slot->question_count)
            ->values();

        if ($questions->count() < $slot->question_count) {
            throw new RuntimeException('A random question pool does not have enough ready questions.');
        }

        return $questions;
    }

    /**
     * @return array<int, int>|null
     */
    private function optionOrder(QuestionVersion $question, int $seed, bool $shuffle): ?array
    {
        if (! in_array($question->type, [QuestionType::SingleChoice, QuestionType::MultipleChoice, QuestionType::TrueFalse], true)) {
            return null;
        }

        $optionIds = $question->options->sortBy('position')->pluck('id')->values();

        if (! $shuffle) {
            return $optionIds->all();
        }

        return $optionIds
            ->sortBy(fn (int $optionId) => crc32($seed.'-'.$question->id.'-'.$optionId))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function questionSnapshot(QuestionVersion $question): array
    {
        return [
            'question_version_id' => $question->id,
            'question_bank_entry_id' => $question->question_bank_entry_id,
            'version_number' => $question->version_number,
            'type' => $question->type->value,
            'difficulty' => $question->difficulty,
            'question_text' => $question->question_text,
            'image_path' => $question->image_path,
            'default_marks' => (string) $question->default_marks,
            'negative_marks' => (string) $question->negative_marks,
            'grading_rules' => $question->grading_rules,
            'general_feedback' => $question->general_feedback,
            'explanation' => $question->explanation,
            'options' => $question->options->sortBy('position')->map(fn ($option) => [
                'id' => $option->id,
                'position' => $option->position,
                'option_text' => $option->option_text,
                'fraction' => (string) $option->fraction,
                'feedback' => $option->feedback,
            ])->values()->all(),
        ];
    }
}
