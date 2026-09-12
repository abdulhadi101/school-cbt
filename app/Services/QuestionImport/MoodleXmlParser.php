<?php

namespace App\Services\QuestionImport;

use App\Enums\QuestionType;
use Illuminate\Support\Str;

final class MoodleXmlParser
{
    /**
     * @return array{questions: array<int, ParsedQuestion>, errors: array<int, array{block: int, message: string}>}
     */
    public static function parse(string $xml): array
    {
        $questions = [];
        $errors = [];

        libxml_use_internal_errors(true);
        $quiz = simplexml_load_string(trim($xml));

        if ($quiz === false) {
            return ['questions' => [], 'errors' => [['block' => 0, 'message' => 'Invalid XML file.']]];
        }

        $blockNo = 0;

        foreach ($quiz->question as $node) {
            $blockNo++;
            $kind = strtolower((string) ($node['type'] ?? ''));

            if (in_array($kind, ['', 'category'], true)) {
                continue;
            }

            $title = trim((string) ($node->name->text ?? ''));
            $stem = trim((string) ($node->questiontext->text ?? ''));
            $generalFeedback = trim((string) ($node->generalfeedback->text ?? '')) ?: null;

            if ($stem === '') {
                $errors[] = ['block' => $blockNo, 'message' => "Question {$blockNo}: missing <questiontext>."];

                continue;
            }

            $answers = [];

            foreach ($node->answer as $answer) {
                $fraction = ((float) ($answer['fraction'] ?? 0)) / 100;
                $answers[] = [
                    'option_text' => trim((string) ($answer->text ?? '')),
                    'fraction' => max(0.0, min(1.0, round($fraction, 4))),
                    'feedback' => trim((string) ($answer->feedback->text ?? '')) ?: null,
                ];
            }

            $answers = array_values(array_filter($answers, fn (array $a): bool => $a['option_text'] !== ''));

            switch ($kind) {
                case 'multichoice':
                    if (count($answers) < 2) {
                        $errors[] = ['block' => $blockNo, 'message' => "Question {$blockNo}: multichoice needs at least two answers."];

                        continue 2;
                    }
                    $single = strtolower(trim((string) ($node->single->text ?? 'true')));
                    $correct = count(array_filter($answers, fn (array $a): bool => $a['fraction'] > 0));
                    $type = $single === 'false' || $correct > 1
                        ? QuestionType::MultipleChoice->value
                        : QuestionType::SingleChoice->value;
                    break;
                case 'truefalse':
                    $type = QuestionType::TrueFalse->value;
                    break;
                case 'shortanswer':
                    if ($answers === []) {
                        $errors[] = ['block' => $blockNo, 'message' => "Question {$blockNo}: shortanswer needs at least one answer."];

                        continue 2;
                    }
                    $type = QuestionType::ShortAnswer->value;
                    break;
                case 'numerical':
                    $type = QuestionType::Numerical->value;
                    $rules = [];
                    $first = $answers[0] ?? null;

                    if ($first !== null && is_numeric($first['option_text'])) {
                        $tolerance = (float) ($node->answer->tolerance->text ?? 0);
                        $rules = ['answer' => (float) $first['option_text'], 'tolerance' => $tolerance];
                    }
                    $questions[] = new ParsedQuestion(
                        questionText: $stem,
                        type: $type,
                        options: [],
                        title: $title !== '' ? $title : Str::limit(strip_tags($stem), 120),
                        generalFeedback: $generalFeedback,
                        gradingRules: $rules,
                    );

                    continue 2;
                case 'essay':
                    $type = QuestionType::Essay->value;
                    $questions[] = new ParsedQuestion(
                        questionText: $stem,
                        type: $type,
                        options: [],
                        title: $title !== '' ? $title : Str::limit(strip_tags($stem), 120),
                        generalFeedback: $generalFeedback,
                    );

                    continue 2;
                default:
                    $errors[] = ['block' => $blockNo, 'message' => "Question {$blockNo}: unsupported type '{$kind}'."];

                    continue 2;
            }

            $questions[] = new ParsedQuestion(
                questionText: $stem,
                type: $type,
                options: $answers,
                title: $title !== '' ? $title : Str::limit(strip_tags($stem), 120),
                generalFeedback: $generalFeedback,
            );
        }

        if ($questions === [] && $errors === []) {
            $errors[] = ['block' => 0, 'message' => 'No importable questions found in XML.'];
        }

        return ['questions' => $questions, 'errors' => $errors];
    }
}
