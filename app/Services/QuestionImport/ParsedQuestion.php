<?php

namespace App\Services\QuestionImport;

class ParsedQuestion
{
    /**
     * @param  array<int, array{option_text: string, fraction: float, feedback: ?string}>  $options
     * @param  array<string, mixed>  $gradingRules
     */
    public function __construct(
        public string $questionText,
        public string $type,
        public array $options = [],
        public ?string $title = null,
        public ?string $generalFeedback = null,
        public ?string $explanation = null,
        public array $gradingRules = [],
    ) {}
}
