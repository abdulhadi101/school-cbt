<?php

namespace App\Services\Exams;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExamRevisionPublisher
{
    public function publish(Exam $exam, User $publisher): ExamRevision
    {
        return DB::transaction(function () use ($exam, $publisher) {
            $exam = Exam::query()
                ->with('draftSlots')
                ->lockForUpdate()
                ->findOrFail($exam->id);

            if ($exam->draftSlots->isEmpty()) {
                throw new RuntimeException('An exam must have at least one slot before publication.');
            }

            $computedTotal = $exam->draftSlots->sum(
                fn ($slot) => (float) $slot->marks_per_question * (int) $slot->question_count
            );

            if (round($computedTotal, 2) !== round((float) $exam->total_marks, 2)) {
                throw new RuntimeException('Exam slot marks must equal the configured total marks.');
            }

            $nextRevisionNumber = ((int) $exam->revisions()->max('revision_number')) + 1;

            $revision = $exam->revisions()->create([
                'revision_number' => $nextRevisionNumber,
                'settings_snapshot' => $this->settingsSnapshot($exam),
                'total_marks' => $exam->total_marks,
                'published_by' => $publisher->id,
                'published_at' => now(),
            ]);

            foreach ($exam->draftSlots as $slot) {
                $revision->slots()->create([
                    'slot_type' => $slot->slot_type,
                    'question_version_id' => $slot->question_version_id,
                    'question_category_id' => $slot->question_category_id,
                    'tag_ids' => $slot->tag_ids,
                    'difficulty' => $slot->difficulty,
                    'question_count' => $slot->question_count,
                    'marks_per_question' => $slot->marks_per_question,
                    'position' => $slot->position,
                ]);
            }

            $exam->forceFill([
                'status' => ExamStatus::Published,
                'approved_by' => $exam->approved_by ?: $publisher->id,
                'approved_at' => $exam->approved_at ?: now(),
            ])->save();

            return $revision->load('slots');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(Exam $exam): array
    {
        return [
            'title' => $exam->title,
            'description' => $exam->description,
            'instructions' => $exam->instructions,
            'exam_type' => $exam->exam_type,
            'subject_id' => $exam->subject_id,
            'term_id' => $exam->term_id,
            'duration_minutes' => $exam->duration_minutes,
            'total_marks' => (string) $exam->total_marks,
            'pass_percentage' => (string) $exam->pass_percentage,
            'max_attempts' => $exam->max_attempts,
            'opens_at' => $exam->opens_at?->toISOString(),
            'closes_at' => $exam->closes_at?->toISOString(),
            'shuffle_questions' => $exam->shuffle_questions,
            'shuffle_options' => $exam->shuffle_options,
            'score_release_policy' => $exam->score_release_policy,
            'score_release_at' => $exam->score_release_at?->toISOString(),
            'show_responses' => $exam->show_responses,
            'show_correct_answers' => $exam->show_correct_answers,
            'show_feedback' => $exam->show_feedback,
        ];
    }
}
