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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptGradingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_autosave_does_not_overwrite_newer_answer(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-001');
        $exam = $this->createExam(['total_marks' => 2]);
        $question = $this->createSingleChoiceQuestion('Capital of Nigeria?');
        $this->addFixedSlot($exam, $question->id, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attemptQuestion = $attempt->questions->first();
        $options = $attemptQuestion->question_snapshot['options'];
        $correctId = collect($options)->firstWhere('fraction', '1.0000')['id']
            ?? collect($options)->firstWhere('fraction', '1')['id'];
        $wrongId = collect($options)->firstWhere(fn ($o) => (int) $o['id'] !== (int) $correctId)['id'];

        $autosaver = app(AnswerAutosaver::class);
        $autosaver->save($attempt, $attemptQuestion, ['selected_option_id' => $correctId], 2);
        $stale = $autosaver->save($attempt, $attemptQuestion, ['selected_option_id' => $wrongId], 1);

        $this->assertSame(2, $stale->client_sequence);
        $this->assertSame($correctId, $stale->response['selected_option_id']);
        $this->assertSame(2, $stale->revisions()->count());
    }

    public function test_unanswered_questions_count_as_zero_in_denominator(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-002');
        $exam = $this->createExam(['total_marks' => 4]);
        $q1 = $this->createSingleChoiceQuestion('Q1?');
        $q2 = $this->createSingleChoiceQuestion('Q2?');
        $this->addFixedSlot($exam, $q1->id, 2, 1);
        $this->addFixedSlot($exam, $q2->id, 2, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $first = $attempt->questions->sortBy('position')->values()->first();
        $correctId = collect($first->question_snapshot['options'])->firstWhere('fraction', '1.0000')['id']
            ?? collect($first->question_snapshot['options'])->firstWhere('fraction', '1')['id'];

        app(AnswerAutosaver::class)->save($attempt, $first, ['selected_option_id' => $correctId], 1);

        $submitted = app(AttemptSubmitter::class)->submit($attempt);

        $this->assertSame(AttemptStatus::Graded, $submitted->status);
        $this->assertEquals(2.0, (float) $submitted->score);
        $this->assertEquals(4.0, (float) $submitted->max_score);
        $this->assertEquals(50.0, (float) $submitted->percentage);
    }

    public function test_essay_attempt_moves_to_grading_not_graded(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-003');
        $exam = $this->createExam(['total_marks' => 5]);
        $mcq = $this->createSingleChoiceQuestion('MCQ?');
        $essay = $this->createEssayQuestion('Explain photosynthesis.');
        $this->addFixedSlot($exam, $mcq->id, 2, 1);
        $this->addFixedSlot($exam, $essay->id, 3, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $ordered = $attempt->questions->sortBy('position')->values();
        $mcqAq = $ordered[0];
        $essayAq = $ordered[1];
        $correctId = collect($mcqAq->question_snapshot['options'])->firstWhere('fraction', '1.0000')['id']
            ?? collect($mcqAq->question_snapshot['options'])->firstWhere('fraction', '1')['id'];

        app(AnswerAutosaver::class)->save($attempt, $mcqAq, ['selected_option_id' => $correctId], 1);
        app(AnswerAutosaver::class)->save($attempt, $essayAq, ['answer' => 'Plants convert light...'], 1);

        $submitted = app(AttemptSubmitter::class)->submit($attempt);

        $this->assertSame(AttemptStatus::Grading, $submitted->status);
        $this->assertNull($submitted->graded_at);
        $statuses = $submitted->answers->pluck('grading_status')->all();
        $this->assertContains(GradingStatus::AutoGraded, $statuses);
        $this->assertContains(GradingStatus::NeedsGrading, $statuses);
    }

    public function test_short_answer_is_case_and_space_insensitive(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-004');
        $exam = $this->createExam(['total_marks' => 1]);
        $question = $this->createShortAnswerQuestion('Capital of Nigeria?', ['Abuja']);
        $this->addFixedSlot($exam, $question->id, 1);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $aq = $attempt->questions->first();
        app(AnswerAutosaver::class)->save($attempt, $aq, ['answer' => '  ABUJA '], 1);
        $submitted = app(AttemptSubmitter::class)->submit($attempt);

        $this->assertSame(AttemptStatus::Graded, $submitted->status);
        $this->assertEquals(1.0, (float) $submitted->score);
    }

    public function test_numerical_tolerance_boundary(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->createExam(['total_marks' => 1]);
        $question = $this->createNumericalQuestion('Value of pi (2dp)?', 3.14, 0.01);
        $this->addFixedSlot($exam, $question->id, 1);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $pass = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->createStudent('STD-005'));
        app(AnswerAutosaver::class)->save($pass, $pass->questions->first(), ['answer' => '3.145'], 1);
        $this->assertEquals(1.0, (float) app(AttemptSubmitter::class)->submit($pass)->score);

        $fail = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->createStudent('STD-006'));
        app(AnswerAutosaver::class)->save($fail, $fail->questions->first(), ['answer' => '3.20'], 1);
        $this->assertEquals(0.0, (float) app(AttemptSubmitter::class)->submit($fail)->score);
    }

    public function test_submit_is_idempotent(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-007');
        $exam = $this->createExam(['total_marks' => 1]);
        $question = $this->createSingleChoiceQuestion('Q?');
        $this->addFixedSlot($exam, $question->id, 1);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $aq = $attempt->questions->first();
        $correctId = collect($aq->question_snapshot['options'])->firstWhere('fraction', '1.0000')['id']
            ?? collect($aq->question_snapshot['options'])->firstWhere('fraction', '1')['id'];
        app(AnswerAutosaver::class)->save($attempt, $aq, ['selected_option_id' => $correctId], 1);

        $first = app(AttemptSubmitter::class)->submit($attempt);
        $second = app(AttemptSubmitter::class)->submit($attempt->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(AttemptStatus::Graded, $second->status);
        $this->assertSame(1, $student->attempts()->count());
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
        $question = $this->createBaseQuestion($text, QuestionType::SingleChoice, ['correct_option_ids' => []]);

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

    private function createShortAnswerQuestion(string $text, array $accepted): QuestionVersion
    {
        return $this->createBaseQuestion($text, QuestionType::ShortAnswer, ['accepted_answers' => $accepted]);
    }

    private function createNumericalQuestion(string $text, float $answer, float $tolerance): QuestionVersion
    {
        return $this->createBaseQuestion($text, QuestionType::Numerical, [
            'answer' => $answer,
            'tolerance' => $tolerance,
        ]);
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
