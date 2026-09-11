<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\GradingStatus;
use App\Enums\QuestionType;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Attempts\AnswerAutosaver;
use App\Services\Attempts\AttemptStarter;
use App\Services\Attempts\AttemptSubmitter;
use App\Services\Exams\ExamRevisionPublisher;
use App\Services\Grading\ManualGrader;
use App\Services\Results\AttemptReleaser;
use App\Services\Results\AttemptResultPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualGradingAndReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_grading_finalizes_essay_attempt(): void
    {
        $publisher = User::factory()->create();
        $grader = User::factory()->create();
        $student = $this->createStudent('STD-101');
        $exam = $this->createExam(['total_marks' => 5]);
        $mcq = $this->createSingleChoiceQuestion('MCQ?');
        $essay = $this->createEssayQuestion('Explain photosynthesis.');
        $this->addFixedSlot($exam, $mcq->id, 2, 1);
        $this->addFixedSlot($exam, $essay->id, 3, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $ordered = $attempt->questions->sortBy('position')->values();
        $correctId = $this->correctOptionId($ordered[0]);
        app(AnswerAutosaver::class)->save($attempt, $ordered[0], ['selected_option_id' => $correctId], 1);
        app(AnswerAutosaver::class)->save($attempt, $ordered[1], ['answer' => 'Plants convert light...'], 1);
        $submitted = app(AttemptSubmitter::class)->submit($attempt);
        $this->assertSame(AttemptStatus::Grading, $submitted->status);

        $essayAnswer = $submitted->answers->firstWhere('attempt_question_id', $ordered[1]->id);
        $graded = app(ManualGrader::class)->grade($essayAnswer, $grader, 2.5, 'Good structure.', 'Partial credit');

        $this->assertSame(GradingStatus::ManuallyGraded, $graded->grading_status);
        $this->assertSame(1, $essayAnswer->manualGrades()->count());
        $this->assertTrue($submitted->events()->where('event_type', 'manual_grade')->exists());

        $final = $submitted->fresh(['questions', 'answers']);
        $this->assertSame(AttemptStatus::Graded, $final->status);
        $this->assertNotNull($final->graded_at);
        $this->assertEquals(4.5, (float) $final->score);
        $this->assertEquals(5.0, (float) $final->max_score);
        $this->assertEquals(90.0, (float) $final->percentage);
    }

    public function test_manual_grading_rejects_out_of_range_score(): void
    {
        $publisher = User::factory()->create();
        $grader = User::factory()->create();
        $student = $this->createStudent('STD-102');
        $exam = $this->createExam(['total_marks' => 3]);
        $essay = $this->createEssayQuestion('Essay?');
        $this->addFixedSlot($exam, $essay->id, 3);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        app(AnswerAutosaver::class)->save($attempt, $attempt->questions->first(), ['answer' => 'Text'], 1);
        $submitted = app(AttemptSubmitter::class)->submit($attempt);
        $answer = $submitted->answers->first();

        $this->expectException(\RuntimeException::class);
        app(ManualGrader::class)->grade($answer, $grader, 5);
    }

    public function test_student_payload_hides_unreleased_results_and_keys(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-103');
        $exam = $this->createExam([
            'total_marks' => 2,
            'score_release_policy' => 'manual',
            'show_responses' => true,
            'show_correct_answers' => true,
            'show_feedback' => true,
        ]);
        $question = $this->createSingleChoiceQuestion('Capital of Nigeria?');
        $this->addFixedSlot($exam, $question->id, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $aq = $attempt->questions->first();
        app(AnswerAutosaver::class)->save($attempt, $aq, ['selected_option_id' => $this->correctOptionId($aq)], 1);
        $submitted = app(AttemptSubmitter::class)->submit($attempt);
        $this->assertSame(AttemptStatus::Graded, $submitted->status);

        $payload = app(AttemptResultPresenter::class)->presentForStudent($submitted->fresh());

        $this->assertFalse($payload['released']);
        $this->assertNull($payload['score']);
        $this->assertNull($payload['questions'][0]['response']);
        $this->assertNull($payload['questions'][0]['answer_key']);

        $flat = json_encode($payload);
        $this->assertStringNotContainsString('fraction', $flat);
        $this->assertStringNotContainsString('grading_rules', $flat);
        $this->assertStringNotContainsString('correct_option', $flat);
    }

    public function test_manual_release_reveals_allowed_fields_without_leaking_keys(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-104');
        $exam = $this->createExam([
            'total_marks' => 2,
            'score_release_policy' => 'manual',
            'show_responses' => true,
            'show_correct_answers' => true,
            'show_feedback' => true,
        ]);
        $question = $this->createSingleChoiceQuestion('Capital of Nigeria?');
        $this->addFixedSlot($exam, $question->id, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $aq = $attempt->questions->first();
        $correctId = $this->correctOptionId($aq);
        app(AnswerAutosaver::class)->save($attempt, $aq, ['selected_option_id' => $correctId], 1);
        $submitted = app(AttemptSubmitter::class)->submit($attempt);

        $released = app(AttemptReleaser::class)->release($submitted, $publisher);
        $this->assertSame(AttemptStatus::Released, $released->status);

        $payload = app(AttemptResultPresenter::class)->presentForStudent($released->fresh());

        $this->assertTrue($payload['released']);
        $this->assertEquals(2.0, (float) $payload['score']);
        $this->assertSame($correctId, $payload['questions'][0]['response']['selected_option_id']);
        $this->assertContains($correctId, $payload['questions'][0]['answer_key']);

        $flat = json_encode($payload);
        $this->assertStringNotContainsString('fraction', $flat);
        $this->assertStringNotContainsString('grading_rules', $flat);
    }

    public function test_immediate_policy_releases_without_explicit_action(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-105');
        $exam = $this->createExam([
            'total_marks' => 1,
            'score_release_policy' => 'immediate',
            'show_responses' => false,
            'show_correct_answers' => false,
            'show_feedback' => false,
        ]);
        $question = $this->createSingleChoiceQuestion('Q?');
        $this->addFixedSlot($exam, $question->id, 1);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $aq = $attempt->questions->first();
        app(AnswerAutosaver::class)->save($attempt, $aq, ['selected_option_id' => $this->correctOptionId($aq)], 1);
        $submitted = app(AttemptSubmitter::class)->submit($attempt);

        $payload = app(AttemptResultPresenter::class)->presentForStudent($submitted->fresh());

        $this->assertTrue($payload['released']);
        $this->assertEquals(1.0, (float) $payload['score']);
        $this->assertNull($payload['questions'][0]['response']);
        $this->assertNull($payload['questions'][0]['answer_key']);
    }

    private function correctOptionId($attemptQuestion): int
    {
        $options = $attemptQuestion->question_snapshot['options'];

        return (int) collect($options)->firstWhere(fn ($o) => (float) $o['fraction'] >= 1)['id'];
    }

    private function createStudent(string $admission): Student
    {
        return Student::query()->create([
            'admission_number' => $admission,
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);
    }

    private function createExam(array $overrides = []): Exam
    {
        return Exam::query()->create(array_merge([
            'subject_id' => Subject::query()->firstOrCreate(['name' => 'Mathematics'])->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'First CA',
            'exam_type' => 'ca',
            'status' => ExamStatus::Approved,
            'duration_minutes' => 30,
            'total_marks' => 1,
            'pass_percentage' => 50,
            'max_attempts' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'score_release_policy' => 'manual',
            'show_responses' => false,
            'show_correct_answers' => false,
            'show_feedback' => false,
        ], $overrides));
    }

    private function addFixedSlot(Exam $exam, int $questionVersionId, float $marks, int $position = 1): void
    {
        $exam->draftSlots()->create([
            'slot_type' => 'fixed_question',
            'question_version_id' => $questionVersionId,
            'question_count' => 1,
            'marks_per_question' => $marks,
            'position' => $position,
        ]);
    }

    private function createSingleChoiceQuestion(string $text): QuestionVersion
    {
        $question = $this->createBaseQuestion($text, QuestionType::SingleChoice, []);

        foreach (['Abuja', 'Lagos', 'Kano', 'Ibadan'] as $position => $optionText) {
            QuestionOption::query()->create([
                'question_version_id' => $question->id,
                'position' => $position + 1,
                'option_text' => $optionText,
                'fraction' => $position === 0 ? 1 : 0,
            ]);
        }

        return $question->load('options');
    }

    private function createEssayQuestion(string $text): QuestionVersion
    {
        return $this->createBaseQuestion($text, QuestionType::Essay, []);
    }

    private function createBaseQuestion(string $text, QuestionType $type, array $gradingRules): QuestionVersion
    {
        $subject = Subject::query()->firstOrCreate(['name' => 'Mathematics']);
        $classLevel = ClassLevel::query()->firstOrCreate(['name' => 'JSS 1']);
        $category = QuestionCategory::query()->create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'name' => 'General',
            'slug' => 'general-'.uniqid(),
        ]);
        $entry = QuestionBankEntry::query()->create([
            'category_id' => $category->id,
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'status' => 'ready',
            'title' => $text,
        ]);

        return QuestionVersion::query()->create([
            'question_bank_entry_id' => $entry->id,
            'version_number' => 1,
            'type' => $type,
            'difficulty' => 'easy',
            'question_text' => $text,
            'default_marks' => 1,
            'negative_marks' => 0,
            'grading_rules' => $gradingRules,
            'content_hash' => hash('sha256', $text.uniqid()),
            'ready_at' => now(),
        ]);
    }
}
