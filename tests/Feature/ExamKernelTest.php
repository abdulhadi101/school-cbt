<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
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
use App\Services\Attempts\AttemptStarter;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExamKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_copies_draft_slots_to_an_immutable_revision(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->createExam(['total_marks' => 2]);
        $question = $this->createQuestionVersion(questionText: 'What is 1 + 1?');

        $exam->draftSlots()->create([
            'slot_type' => 'fixed_question',
            'question_version_id' => $question->id,
            'question_count' => 1,
            'marks_per_question' => 2,
            'position' => 1,
        ]);

        $revision = app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $this->assertSame(1, $revision->revision_number);
        $this->assertSame('2.00', $revision->total_marks);
        $this->assertCount(1, $revision->slots);
        $this->assertSame($question->id, $revision->slots->first()->question_version_id);
        $this->assertSame(ExamStatus::Published, $exam->fresh()->status);

        $exam->draftSlots()->first()->update(['marks_per_question' => 5]);

        $this->assertSame('2.00', $revision->fresh()->slots()->first()->marks_per_question);
    }

    public function test_publishing_rejects_total_mark_mismatches(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->createExam(['total_marks' => 10]);
        $question = $this->createQuestionVersion();

        $exam->draftSlots()->create([
            'slot_type' => 'fixed_question',
            'question_version_id' => $question->id,
            'question_count' => 1,
            'marks_per_question' => 2,
            'position' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Exam slot marks must equal the configured total marks.');

        app(ExamRevisionPublisher::class)->publish($exam, $publisher);
    }

    public function test_attempt_start_materializes_question_snapshot_and_resumes_active_attempt(): void
    {
        $publisher = User::factory()->create();
        $student = Student::query()->create([
            'admission_number' => 'STD-001',
            'first_name' => 'Amina',
            'last_name' => 'Lawal',
        ]);
        $exam = $this->createExam(['total_marks' => 2, 'shuffle_options' => true]);
        $question = $this->createQuestionVersion(questionText: 'Capital of Nigeria?');

        $exam->draftSlots()->create([
            'slot_type' => 'fixed_question',
            'question_version_id' => $question->id,
            'question_count' => 1,
            'marks_per_question' => 2,
            'position' => 1,
        ]);

        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attemptQuestion = $attempt->questions->first();

        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame('Capital of Nigeria?', $attemptQuestion->question_snapshot['question_text']);
        $this->assertCount(4, $attemptQuestion->option_order);

        $question->update(['question_text' => 'Edited after attempt started']);

        $resumed = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);

        $this->assertSame($attempt->id, $resumed->id);
        $this->assertSame('Capital of Nigeria?', $resumed->questions->first()->question_snapshot['question_text']);
    }

    private function createExam(array $overrides = []): Exam
    {
        return Exam::query()->create(array_merge([
            'subject_id' => Subject::query()->create(['name' => 'Mathematics'])->id,
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

    private function createQuestionVersion(string $questionText = 'Question?'): QuestionVersion
    {
        $subject = Subject::query()->first() ?? Subject::query()->create(['name' => 'Mathematics']);
        $classLevel = ClassLevel::query()->first() ?? ClassLevel::query()->create(['name' => 'JSS 1']);
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
            'title' => $questionText,
        ]);
        $question = QuestionVersion::query()->create([
            'question_bank_entry_id' => $entry->id,
            'version_number' => 1,
            'type' => QuestionType::SingleChoice,
            'difficulty' => 'easy',
            'question_text' => $questionText,
            'default_marks' => 1,
            'negative_marks' => 0,
            'grading_rules' => ['correct_option_ids' => []],
            'content_hash' => hash('sha256', $questionText),
            'ready_at' => now(),
        ]);

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
}
