<?php

namespace Tests\Unit;

use App\Services\QuestionImport\AikenParser;
use App\Services\QuestionImport\GiftParser;
use App\Services\QuestionImport\MoodleXmlParser;
use PHPUnit\Framework\TestCase;

class QuestionImportParserTest extends TestCase
{
    public function test_aiken_parses_question_with_answer_key(): void
    {
        $result = AikenParser::parse("Q1. What is a pawpaw?\nA. a place\nB. a town\nC. a fruit\nD. All of the above\nANSWER: C");

        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['questions']);
        $this->assertSame('What is a pawpaw?', $result['questions'][0]->questionText);
        $this->assertSame('single_choice', $result['questions'][0]->type);
        $this->assertSame(1.0, $result['questions'][0]->options[2]['fraction']);
        $this->assertSame(0.0, $result['questions'][0]->options[0]['fraction']);
    }

    public function test_aiken_reports_missing_answer(): void
    {
        $result = AikenParser::parse("What is 2 + 2?\nA. 3\nB. 4");

        $this->assertCount(0, $result['questions']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_aiken_detects_true_false(): void
    {
        $result = AikenParser::parse("The sky is blue.\nA. True\nB. False\nANSWER: A");

        $this->assertSame('true_false', $result['questions'][0]->type);
    }

    public function test_gift_parses_single_choice_and_true_false(): void
    {
        $choice = GiftParser::parse("What is 2 + 2? {\n= 4\n~ 3\n~ 5\n}");
        $this->assertSame('single_choice', $choice['questions'][0]->type);
        $this->assertCount(0, $choice['errors']);

        $tf = GiftParser::parse('The sky is blue. {T}');
        $this->assertSame('true_false', $tf['questions'][0]->type);
    }

    public function test_gift_parses_numerical(): void
    {
        $result = GiftParser::parse('What is pi? {#3.14:0.01}');

        $this->assertSame('numerical', $result['questions'][0]->type);
        $this->assertSame(3.14, $result['questions'][0]->gradingRules['answer']);
    }

    public function test_gift_parses_multiple_correct_with_split_fractions(): void
    {
        $result = GiftParser::parse("Which are even? {\n= 2\n= 4\n~ 3\n}");

        $this->assertCount(0, $result['errors']);
        $this->assertSame('multiple_choice', $result['questions'][0]->type);
        $this->assertSame([0.5, 0.5, 0.0], array_column($result['questions'][0]->options, 'fraction'));
    }

    public function test_gift_parses_short_answer_and_essay(): void
    {
        $short = GiftParser::parse("Capital of France? {\n= Paris\n= paris\n}");
        $this->assertSame('short_answer', $short['questions'][0]->type);
        $this->assertCount(2, $short['questions'][0]->options);

        $essay = GiftParser::parse('Discuss photosynthesis. {}');
        $this->assertSame('essay', $essay['questions'][0]->type);
    }

    public function test_gift_reports_missing_answer_block(): void
    {
        $result = GiftParser::parse('Just a statement with no braces');

        $this->assertCount(0, $result['questions']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_moodle_xml_parses_truefalse_and_rejects_unknown_type(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<quiz>
  <question type="truefalse">
    <name><text>TF</text></name>
    <questiontext><text>The sky is blue.</text></questiontext>
    <answer fraction="100"><text>true</text></answer>
    <answer fraction="0"><text>false</text></answer>
  </question>
  <question type="matching">
    <name><text>M</text></name>
    <questiontext><text>Match these.</text></questiontext>
  </question>
</quiz>
XML;

        $result = MoodleXmlParser::parse($xml);

        $this->assertCount(1, $result['questions']);
        $this->assertSame('true_false', $result['questions'][0]->type);
        $this->assertCount(1, $result['errors']);
    }

    public function test_moodle_xml_parses_multichoice(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<quiz>
  <question type="multichoice">
    <name><text>Simple</text></name>
    <questiontext format="html"><text>What is 2 + 2?</text></questiontext>
    <single>true</single>
    <answer fraction="100"><text>4</text></answer>
    <answer fraction="0"><text>5</text></answer>
  </question>
</quiz>
XML;

        $result = MoodleXmlParser::parse($xml);

        $this->assertCount(0, $result['errors']);
        $this->assertSame('single_choice', $result['questions'][0]->type);
        $this->assertSame('What is 2 + 2?', $result['questions'][0]->questionText);
    }

    public function test_moodle_xml_rejects_invalid_xml(): void
    {
        $result = MoodleXmlParser::parse('not xml');

        $this->assertCount(0, $result['questions']);
        $this->assertNotEmpty($result['errors']);
    }
}
