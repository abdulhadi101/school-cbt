<?php

namespace App\Services\Attempts;

use App\Models\Attempt;

class AttemptTakingPresenter
{
    /**
     * Active-attempt payload. Includes the student's current responses, but never answer keys.
     *
     * @return array<string, mixed>
     */
    public function present(Attempt $attempt): array
    {
        $attempt->loadMissing(['exam.subject', 'questions.answer']);

        return [
            'attempt_id' => $attempt->id,
            'exam_id' => $attempt->exam_id,
            'status' => $attempt->status->value,
            'started_at' => $attempt->started_at,
            'deadline_at' => $attempt->deadline_at,
            'last_seen_at' => $attempt->last_seen_at,
            'exam' => [
                'title' => $attempt->exam->title,
                'subject' => $attempt->exam->subject?->name,
                'duration_minutes' => $attempt->exam->duration_minutes,
                'instructions' => $attempt->exam->instructions,
            ],
            'questions' => $attempt->questions->sortBy('position')->values()->map(fn ($attemptQuestion) => [
                'attempt_question_id' => $attemptQuestion->id,
                'position' => $attemptQuestion->position,
                'marks' => $attemptQuestion->marks,
                'type' => $attemptQuestion->question_snapshot['type'],
                'question_text' => $attemptQuestion->question_snapshot['question_text'],
                'image_path' => $attemptQuestion->question_snapshot['image_path'] ?? null,
                'options' => $this->orderedOptions($attemptQuestion->question_snapshot, $attemptQuestion->option_order),
                'response' => $attemptQuestion->answer?->response,
                'client_sequence' => $attemptQuestion->answer?->client_sequence ?? 0,
            ])->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, option_text: string, position: int}>
     */
    private function orderedOptions(array $snapshot, ?array $optionOrder): array
    {
        $options = collect($snapshot['options'] ?? [])->map(fn ($option) => [
            'id' => $option['id'],
            'option_text' => $option['option_text'],
            'position' => $option['position'],
        ])->keyBy('id');

        if (! $optionOrder) {
            return $options->sortBy('position')->values()->all();
        }

        return collect($optionOrder)
            ->map(fn ($id) => $options->get($id))
            ->filter()
            ->values()
            ->all();
    }
}
