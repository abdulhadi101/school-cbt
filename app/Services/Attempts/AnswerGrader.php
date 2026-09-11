<?php

namespace App\Services\Attempts;

use App\Enums\GradingStatus;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;

class AnswerGrader
{
    public function grade(AttemptAnswer $answer): AttemptAnswer
    {
        $attemptQuestion = $answer->attemptQuestion;
        $snapshot = $attemptQuestion->question_snapshot;
        $type = QuestionType::from($snapshot['type']);

        if ($type === QuestionType::Essay) {
            $answer->forceFill([
                'grading_status' => GradingStatus::NeedsGrading,
                'score' => null,
                'is_correct' => null,
                'graded_at' => null,
            ])->save();

            return $answer;
        }

        $scoreFraction = match ($type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse => $this->singleChoiceFraction($answer, $snapshot),
            QuestionType::MultipleChoice => $this->multipleChoiceFraction($answer, $snapshot),
            QuestionType::ShortAnswer => $this->shortAnswerFraction($answer, $snapshot),
            QuestionType::Numerical => $this->numericalFraction($answer, $snapshot),
            QuestionType::FillBlank => $this->fillBlankFraction($answer, $snapshot),
            QuestionType::Essay => 0.0,
        };

        $score = round(max(0, min(1, $scoreFraction)) * (float) $attemptQuestion->marks, 2);

        $answer->forceFill([
            'grading_status' => GradingStatus::AutoGraded,
            'score' => $score,
            'is_correct' => $scoreFraction >= 1,
            'graded_at' => now(),
        ])->save();

        return $answer;
    }

    private function singleChoiceFraction(AttemptAnswer $answer, array $snapshot): float
    {
        $selectedOptionId = (int) ($answer->response['selected_option_id'] ?? 0);

        foreach ($snapshot['options'] ?? [] as $option) {
            if ((int) $option['id'] === $selectedOptionId) {
                return (float) $option['fraction'];
            }
        }

        return 0.0;
    }

    private function multipleChoiceFraction(AttemptAnswer $answer, array $snapshot): float
    {
        $selectedOptionIds = collect($answer->response['selected_option_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

        if ($selectedOptionIds === []) {
            return 0.0;
        }

        return collect($snapshot['options'] ?? [])
            ->filter(fn (array $option) => in_array((int) $option['id'], $selectedOptionIds, true))
            ->sum(fn (array $option) => (float) $option['fraction']);
    }

    private function shortAnswerFraction(AttemptAnswer $answer, array $snapshot): float
    {
        $given = $this->normalizeText((string) ($answer->response['answer'] ?? ''));

        if ($given === '') {
            return 0.0;
        }

        foreach (($snapshot['grading_rules']['accepted_answers'] ?? []) as $accepted) {
            if ($given === $this->normalizeText((string) $accepted)) {
                return 1.0;
            }
        }

        return 0.0;
    }

    private function numericalFraction(AttemptAnswer $answer, array $snapshot): float
    {
        if (! is_numeric($answer->response['answer'] ?? null)) {
            return 0.0;
        }

        $given = (float) $answer->response['answer'];
        $expected = $snapshot['grading_rules']['answer'] ?? null;
        $tolerance = (float) ($snapshot['grading_rules']['tolerance'] ?? 0);

        if (! is_numeric($expected)) {
            return 0.0;
        }

        return abs($given - (float) $expected) <= $tolerance ? 1.0 : 0.0;
    }

    private function fillBlankFraction(AttemptAnswer $answer, array $snapshot): float
    {
        $givenAnswers = collect($answer->response['answers'] ?? [])->map(fn ($value) => $this->normalizeText((string) $value))->values();
        $expectedAnswers = collect($snapshot['grading_rules']['answers'] ?? [])->map(fn ($value) => $this->normalizeText((string) $value))->values();

        if ($expectedAnswers->isEmpty() || $givenAnswers->count() !== $expectedAnswers->count()) {
            return 0.0;
        }

        return $givenAnswers->diffAssoc($expectedAnswers)->isEmpty() ? 1.0 : 0.0;
    }

    private function normalizeText(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
