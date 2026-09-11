<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Permission;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Attempts\AnswerAutosaver;
use App\Services\Attempts\AttemptExpirer;
use App\Services\Attempts\AttemptStarter;
use App\Services\Attempts\AttemptSubmitter;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptExpiryAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_attempt_is_auto_submitted_and_marked_expired(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-201');
        $exam = $this->createExam(['total_marks' => 2]);
        $question = $this->createSingleChoiceQuestion('Q?');
        $this->addFixedSlot($exam, $question->id, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill(['deadline_at' => now()->subMinute()])->save();

        $count = app(AttemptExpirer::class)->expireDue();

        $this->assertSame(1, $count);
        $fresh = $attempt->fresh();
        $this->assertSame(AttemptStatus::Expired, $fresh->status);
        $this->assertEquals(0.0, (float) $fresh->score);
        $this->assertTrue($fresh->events()->where('event_type', 'attempt_expired')->exists());
        $this->assertSame(0, app(AttemptExpirer::class)->expireDue());
    }

    public function test_expired_essay_attempt_stays_in_grading(): void
    {
        $publisher = User::factory()->create();
        $student = $this->createStudent('STD-202');
        $exam = $this->createExam(['total_marks' => 5]);
        $mcq = $this->createSingleChoiceQuestion('MCQ?');
        $essay = $this->createEssayQuestion('Essay?');
        $this->addFixedSlot($exam, $mcq->id, 2, 1);
        $this->addFixedSlot($exam, $essay->id, 3, 2);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $ordered = $attempt->questions->sortBy('position')->values();
        app(AnswerAutosaver::class)->save($attempt, $ordered[0], ['selected_option_id' => $this->correctOptionId($ordered[0])], 1);
        app(AnswerAutosaver::class)->save($attempt, $ordered[1], ['answer' => 'Text'], 1);
        $attempt->forceFill(['deadline_at' => now()->subMinute()])->save();

        app(AttemptExpirer::class)->expireDue();

        $this->assertSame(AttemptStatus::Grading, $attempt->fresh()->status);
    }

    public function test_student_cannot_view_another_students_result(): void
    {
        $publisher = User::factory()->create();
        $userA = $this->createUserWithPermission('STD-203', 'attempts.take');
        $userB = $this->createUserWithPermission('STD-204', 'attempts.take');
        $exam = $this->createExam(['total_marks' => 1]);
        $question = $this->createSingleChoiceQuestion('Q?');
        $this->addFixedSlot($exam, $question->id, 1);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attemptB = app(AttemptStarter::class)->startOrResume($exam->fresh(), $userB->student);

        $this->actingAs($userA)->getJson("/attempts/{$attemptB->id}/result")->assertForbidden();
        $this->actingAs($userB)->getJson("/attempts/{$attemptB->id}/result")->assertOk();
    }

    public function test_user_without_permission_cannot_start_attempt(): void
    {
        $publisher = User::factory()->create();
        $plain = User::factory()->create();
        $exam = $this->createExam(['total_marks' => 1]);
        $question = $this->createSingleChoiceQuestion('Q?');
        $this->addFixedSlot($exam, $question->id, 1);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $this->actingAs($plain)->postJson('/attempts/start', ['exam_id' => $exam->id])->assertForbidden();
    }

    public function test_grading_requires_permission_and_staff_view_shows_snapshot(): void
    {
        $publisher = User::factory()->create();
        $studentUser = $this->createUserWithPermission('STD-205', 'attempts.take');
        $grader = $this->createUserWithPermission('STAFF-1', 'grades.grade');
        $viewer = $this->createUserWithPermission('STAFF-2', 'grades.view');

        $exam = $this->createExam(['total_marks' => 3]);
        $essay = $this->createEssayQuestion('Essay?');
        $this->addFixedSlot($exam, $essay->id, 3);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $studentUser->student);
        app(AnswerAutosaver::class)->save($attempt, $attempt->questions->first(), ['answer' => 'Text'], 1);
        $submitted = app(AttemptSubmitter::class)->submit($attempt);
        $answer = $submitted->answers->first();

        $plain = User::factory()->create();
        $this->actingAs($plain)->postJson("/staff/answers/{$answer->id}/grade", ['score' => 2])->assertForbidden();

        $this->actingAs($grader)->postJson("/staff/answers/{$answer->id}/grade", [
            'score' => 2,
            'feedback' => 'Good',
        ])->assertOk();

        $this->actingAs($viewer)->getJson("/staff/attempts/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('attempt_id', $attempt->id);
    }

    private function createUserWithPermission(string $admission, string $permission): User
    {
        $user = User::factory()->create();
        $perm = Permission::query()->firstOrCreate(
            ['name' => $permission],
            ['label' => $permission, 'group' => 'test', 'description' => null]
        );
        $role = Role::query()->firstOrCreate(
            ['name' => 'role-'.$permission.'-'.uniqid()],
            ['label' => 'Test', 'description' => null]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        Student::query()->create([
            'user_id' => $user->id,
            'admission_number' => $admission,
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);

        return $user->fresh();
    }

    private function createStudent(string $admission): Student
    {
        return Student::query()->create([
            'admission_number' => $admission,
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);
    }

    private function correctOptionId($attemptQuestion): int
    {
        return (int) collect($attemptQuestion->question_snapshot['options'])
            ->firstWhere(fn ($o) => (float) $o['fraction'] >= 1)['id'];
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
        $question = $this->createBaseQuestion($text, QuestionType::SingleChoice);

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
        return $this->createBaseQuestion($text, QuestionType::Essay);
    }

    private function createBaseQuestion(string $text, QuestionType $type): QuestionVersion
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
            'grading_rules' => [],
            'content_hash' => hash('sha256', $text.uniqid()),
            'ready_at' => now(),
        ]);
    }
}
