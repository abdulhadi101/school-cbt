<?php

namespace App\Services\Results;

use App\Models\Attempt;

class AttemptResultPresenter
{
    public function __construct(private readonly ResultReleasePolicy $policy) {}

    /**
     * Student-safe payload. Never includes grading_rules, fractions,
     * explanations, or unreleased scores.
     */
    public function presentForStudent(Attempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'questions.answer']);
        $exam = $attempt->exam;

        $released = $this->policy->isScoreReleased($exam, $attempt);
        $showResponses = $this->policy->canSeeResponses($exam, $attempt);
        $showCorrect = $this->policy->canSeeCorrectAnswers($exam, $attempt);
        $showFeedback = $this->policy->canSeeFeedback($exam, $attempt);

        return [
            'attempt_id' => $attempt->id,
            'status' => $attempt->status->value,
            'released' => $released,
            'score' => $released ? $attempt->score : null,
            'max_score' => $released ? $attempt->max_score : null,
            'percentage' => $released ? $attempt->percentage : null,
            'questions' => $attempt->questions->sortBy('position')->values()->map(
                fn ($aq) => $this->presentQuestionForStudent($aq, $released, $showResponses, $showCorrect, $showFeedback)
            )->all(),
        ];
    }

    /**
     * Staff payload for grading/reporting. Includes internal grading state.
     */
    public function presentForStaff(Attempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'questions.answer', 'answers']);

        return [
            'attempt_id' => $attempt->id,
            'exam_id' => $attempt->exam_id,
            'student_id' => $attempt->student_id,
            'status' => $attempt->status->value,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'percentage' => $attempt->percentage,
            'submitted_at' => $attempt->submitted_at,
            'graded_at' => $attempt->graded_at,
            'released_at' => $attempt->released_at,
            'questions' => $attempt->questions->sortBy('position')->values()->map(fn ($aq) => [
                'attempt_question_id' => $aq->id,
                'position' => $aq->position,
                'marks' => $aq->marks,
                'requires_manual_grading' => $aq->requires_manual_grading,
                'snapshot' => $aq->question_snapshot,
                'option_order' => $aq->option_order,
                'answer' => $aq->answer ? [
                    'id' => $aq->answer->id,
                    'response' => $aq->answer->response,
                    'grading_status' => $aq->answer->grading_status->value,
                    'score' => $aq->answer->score,
                    'is_correct' => $aq->answer->is_correct,
                    'feedback' => $aq->answer->feedback,
                    'graded_by' => $aq->answer->graded_by,
                    'graded_at' => $aq->answer->graded_at,
                ] : null,
            ])->all(),
        ];
    }

    private function presentQuestionForStudent(
        $attemptQuestion,
        bool $released,
        bool $showResponses,
        bool $showCorrect,
        bool $showFeedback
    ): array {
        $snapshot = $attemptQuestion->question_snapshot;
        $answer = $attemptQuestion->answer;

        $orderedOptions = $this->orderedSafeOptions($snapshot, $attemptQuestion->option_order);

        return [
            'attempt_question_id' => $attemptQuestion->id,
            'position' => $attemptQuestion->position,
            'marks' => $attemptQuestion->marks,
            'type' => $snapshot['type'],
            'question_text' => $snapshot['question_text'],
            'image_path' => $snapshot['image_path'] ?? null,
            'options' => $orderedOptions,
            'response' => $showResponses ? $answer?->response : null,
            'awarded_score' => $released ? $answer?->score : null,
            'is_correct' => $showCorrect ? $answer?->is_correct : null,
            'feedback' => $showFeedback ? $answer?->feedback : null,
            'explanation' => $showFeedback ? ($snapshot['explanation'] ?? null) : null,
            'answer_key' => $showCorrect ? $this->safeAnswerKey($snapshot) : null,
        ];
    }

    private function safeAnswerKey(array $snapshot): array|string|null
    {
        $type = $snapshot['type'];

        if (in_array($type, ['single_choice', 'multiple_choice', 'true_false'], true)) {
            return collect($snapshot['options'] ?? [])
                ->filter(fn ($o) => (float) $o['fraction'] >= 1)
                ->pluck('id')
                ->values()
                ->all();
        }

        return match ($type) {
            'short_answer' => $snapshot['grading_rules']['accepted_answers'] ?? null,
            'numerical' => [
                'answer' => $snapshot['grading_rules']['answer'] ?? null,
                'tolerance' => $snapshot['grading_rules']['tolerance'] ?? 0,
            ],
            'fill_blank' => $snapshot['grading_rules']['answers'] ?? null,
            default => null,
        };
    }

    private function orderedSafeOptions(array $snapshot, ?array $optionOrder): array
    {
        $options = collect($snapshot['options'] ?? [])->map(fn ($o) => [
            'id' => $o['id'],
            'option_text' => $o['option_text'],
            'position' => $o['position'],
        ])->keyBy('id');

        if ($optionOrder === null || $optionOrder === []) {
            return $options->sortBy('position')->values()->all();
        }

        return collect($optionOrder)
            ->map(fn ($id) => $options->get($id))
            ->filter()
            ->values()
            ->all();
    }
}
