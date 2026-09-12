<?php

namespace App\Services\QuestionImport;

use App\Enums\QuestionType;
use Illuminate\Support\Str;

final class GiftParser
{
    /**
     * @return array{questions: array<int, ParsedQuestion>, errors: array<int, array{block: int, message: string}>}
     */
    public static function parse(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        $questions = [];
        $errors = [];

        if ($normalized === '') {
            return ['questions' => [], 'errors' => [['block' => 0, 'message' => 'No content provided.']]];
        }

        $blocks = preg_split("/\n\s*\n/", $normalized) ?: [];

        foreach (array_values($blocks) as $index => $block) {
            $blockNo = $index + 1;
            $block = trim($block);

            if ($block === '' || str_starts_with($block, '//')) {
                continue;
            }

            $title = null;

            if (preg_match('/^::(.*?)::\s*(.*)$/s', $block, $m)) {
                $title = trim($m[1]) ?: null;
                $block = trim($m[2]);
            }

            if (! preg_match('/^(.*?)\{\s*(.*?)\s*\}$/s', $block, $m)) {
                $errors[] = ['block' => $blockNo, 'message' => 'Missing { … } answer block.'];

                continue;
            }

            $stem = trim($m[1]);
            $body = trim($m[2]);

            if ($stem === '') {
                $errors[] = ['block' => $blockNo, 'message' => 'Missing question text before {.'];

                continue;
            }

            $generalFeedback = null;

            if (str_contains($body, '####')) {
                [$body, $generalFeedback] = array_map(trim(...), explode('####', $body, 2));
            }

            $upper = Str::upper(trim($body));

            if (in_array($upper, ['T', 'F', 'TRUE', 'FALSE'], true)) {
                $isTrue = in_array($upper, ['T', 'TRUE'], true);
                $questions[] = new ParsedQuestion(
                    questionText: $stem,
                    type: QuestionType::TrueFalse->value,
                    options: [
                        ['option_text' => 'True', 'fraction' => $isTrue ? 1.0 : 0.0, 'feedback' => null],
                        ['option_text' => 'False', 'fraction' => $isTrue ? 0.0 : 1.0, 'feedback' => null],
                    ],
                    title: $title ?? Str::limit($stem, 120),
                    generalFeedback: $generalFeedback,
                );

                continue;
            }

            if (str_starts_with(trim($body), '#')) {
                $spec = trim(ltrim(trim($body), '#'));
                $parts = explode(':', $spec);
                $answer = trim($parts[0]);

                if (! is_numeric($answer)) {
                    $errors[] = ['block' => $blockNo, 'message' => 'Numerical answer must be numeric, e.g. {#3.14:0.01}.'];

                    continue;
                }

                $tolerance = isset($parts[1]) && is_numeric(trim($parts[1])) ? (float) trim($parts[1]) : 0.0;
                $questions[] = new ParsedQuestion(
                    questionText: $stem,
                    type: QuestionType::Numerical->value,
                    options: [],
                    title: $title ?? Str::limit($stem, 120),
                    generalFeedback: $generalFeedback,
                    gradingRules: ['answer' => (float) $answer, 'tolerance' => $tolerance],
                );

                continue;
            }

            if ($body === '') {
                $questions[] = new ParsedQuestion(
                    questionText: $stem,
                    type: QuestionType::Essay->value,
                    options: [],
                    title: $title ?? Str::limit($stem, 120),
                    generalFeedback: $generalFeedback,
                );

                continue;
            }

            $tokens = preg_split('/\n/', $body) ?: [$body];
            $options = [];
            $correctCount = 0;

            foreach ($tokens as $token) {
                $token = trim($token);

                if ($token === '') {
                    continue;
                }

                if (! preg_match('/^([=~])\s*(.+)$/s', $token, $tm)) {
                    $errors[] = ['block' => $blockNo, 'message' => "Each choice must start with = or ~ (got '{$token}')."];

                    continue 2;
                }

                $isCorrect = $tm[1] === '=';
                $rest = trim($tm[2]);
                $feedback = null;

                if (str_contains($rest, '#')) {
                    [$rest, $feedback] = array_map(trim(...), explode('#', $rest, 2));
                }

                if ($rest === '') {
                    $errors[] = ['block' => $blockNo, 'message' => 'Empty choice text.'];

                    continue 2;
                }

                if ($isCorrect) {
                    $correctCount++;
                }

                $options[] = [
                    'option_text' => $rest,
                    'fraction' => $isCorrect ? 1.0 : 0.0,
                    'feedback' => $feedback,
                    '_correct' => $isCorrect,
                ];
            }

            if (count($options) < 2) {
                if (count($options) >= 1 && $correctCount === count($options)) {
                    $accepted = array_map(fn (array $o): array => [
                        'option_text' => $o['option_text'],
                        'fraction' => 1.0,
                        'feedback' => $o['feedback'],
                    ], $options);
                    $questions[] = new ParsedQuestion(
                        questionText: $stem,
                        type: QuestionType::ShortAnswer->value,
                        options: $accepted,
                        title: $title ?? Str::limit($stem, 120),
                        generalFeedback: $generalFeedback,
                    );

                    continue;
                }

                $errors[] = ['block' => $blockNo, 'message' => 'Need at least two choices (= correct, ~ wrong).'];

                continue;
            }

            $hasWrong = $correctCount < count($options);

            if (! $hasWrong) {
                $accepted = array_map(fn (array $o): array => [
                    'option_text' => $o['option_text'],
                    'fraction' => 1.0,
                    'feedback' => $o['feedback'],
                ], $options);
                $questions[] = new ParsedQuestion(
                    questionText: $stem,
                    type: QuestionType::ShortAnswer->value,
                    options: $accepted,
                    title: $title ?? Str::limit($stem, 120),
                    generalFeedback: $generalFeedback,
                );

                continue;
            }

            $type = $correctCount > 1 ? QuestionType::MultipleChoice->value : QuestionType::SingleChoice->value;
            $fraction = $correctCount > 0 ? round(1 / $correctCount, 4) : 0.0;
            $final = array_map(fn (array $o): array => [
                'option_text' => $o['option_text'],
                'fraction' => $o['_correct'] ? $fraction : 0.0,
                'feedback' => $o['feedback'],
            ], $options);

            $questions[] = new ParsedQuestion(
                questionText: $stem,
                type: $type,
                options: $final,
                title: $title ?? Str::limit($stem, 120),
                generalFeedback: $generalFeedback,
            );
        }

        return ['questions' => $questions, 'errors' => $errors];
    }
}
