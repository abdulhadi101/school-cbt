<?php

namespace App\Services\QuestionImport;

use App\Enums\QuestionType;
use Illuminate\Support\Str;

final class AikenParser
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
            $lines = array_values(array_filter(
                array_map(trim(...), explode("\n", $block)),
                fn (string $line): bool => $line !== ''
            ));

            if ($lines === []) {
                continue;
            }

            $questionText = preg_replace('/^(?:Q\s*\d+\s*[\.\)\:\-]\s*|\d+\s*[\.\)]\s*)/i', '', $lines[0]);
            $questionText = trim((string) $questionText);

            $options = [];
            $answerLetter = null;

            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/^ANSWER\s*:\s*([A-Z])\s*$/i', $line, $m)) {
                    $answerLetter = strtoupper($m[1]);

                    continue;
                }

                if (preg_match('/^([A-Z])\s*[.\)\:\-]\s*(.+)$/i', $line, $m)) {
                    $options[strtoupper($m[1])] = trim($m[2]);

                    continue;
                }

                $errors[] = ['block' => $blockNo, 'message' => "Unrecognised line: '{$line}'. Expected 'A. option' or 'ANSWER: X'."];

                continue 2;
            }

            if ($questionText === '') {
                $errors[] = ['block' => $blockNo, 'message' => 'Missing question text.'];

                continue;
            }

            if (count($options) < 2) {
                $errors[] = ['block' => $blockNo, 'message' => 'Need at least two options (A. … B. …).'];

                continue;
            }

            if ($answerLetter === null) {
                $errors[] = ['block' => $blockNo, 'message' => 'Missing ANSWER: X line.'];

                continue;
            }

            if (! isset($options[$answerLetter])) {
                $errors[] = ['block' => $blockNo, 'message' => "ANSWER: {$answerLetter} does not match any option."];

                continue;
            }

            $isTrueFalse = count($options) === 2
                && collect($options)->map(fn (string $o): string => Str::lower(trim($o)))->sort()->values()->all() === ['false', 'true'];

            $type = $isTrueFalse ? QuestionType::TrueFalse->value : QuestionType::SingleChoice->value;

            $ordered = [];
            $position = 0;

            foreach ($options as $letter => $optionText) {
                $position++;
                $ordered[] = [
                    'option_text' => $optionText,
                    'fraction' => $letter === $answerLetter ? 1.0 : 0.0,
                    'feedback' => null,
                ];
            }

            if ($isTrueFalse) {
                usort($ordered, fn (array $a, array $b): int => strcmp(Str::lower($a['option_text']), Str::lower($b['option_text'])));
                $ordered = array_values($ordered);
            }

            $questions[] = new ParsedQuestion(
                questionText: $questionText,
                type: $type,
                options: $ordered,
                title: Str::limit($questionText, 120),
            );
        }

        return ['questions' => $questions, 'errors' => $errors];
    }
}
