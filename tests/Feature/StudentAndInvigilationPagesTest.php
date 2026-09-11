<?php

namespace Tests\Feature;

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
use App\Services\Attempts\AttemptStarter;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAndInvigilationPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_student_can_open_own_take_page_without_answer_key_leaks(): void
    {
        $publisher = User::factory()->create();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentFor($studentUser, 'STD-301');
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam, $student);

        $response = $this->actingAs($studentUser)->get("/attempts/{$attempt->id}/take");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Student/Attempts/Take')
            ->where('attempt.attempt_id', $attempt->id)
        );

        $payload = $response->getContent();
        $this->assertStringNotContainsString('fraction', $payload);
        $this->assertStringNotContainsString('grading_rules', $payload);
    }

    public function test_student_cannot_open_another_students_take_page(): void
    {
        $publisher = User::factory()->create();
        $owner = $this->userWithPermission('attempts.take');
        $other = $this->userWithPermission('attempts.take');
        $attempt = app(AttemptStarter::class)->startOrResume($this->publishedExam($publisher), $this->studentFor($owner, 'STD-302'));
        $this->studentFor($other, 'STD-303');

        $this->actingAs($other)->get("/attempts/{$attempt->id}/take")->assertForbidden();
    }

    public function test_invigilator_can_view_dashboard_and_status_payload(): void
    {
        $publisher = User::factory()->create();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentFor($studentUser, 'STD-304');
        $exam = $this->publishedExam($publisher);
        app(AttemptStarter::class)->startOrResume($exam, $student);
        $invigilator = $this->userWithPermission('attempts.invigilate');

        $this->actingAs($invigilator)->get("/staff/exams/{$exam->id}/invigilation")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Staff/Invigilation/Show')
                ->where('dashboard.summary.total_attempts', 1)
            );

        $this->actingAs($invigilator)->getJson("/staff/exams/{$exam->id}/invigilation/status")
            ->assertOk()
            ->assertJsonPath('summary.total_attempts', 1)
            ->assertJsonPath('attempts.0.admission_number', 'STD-304');
    }

    public function test_invigilation_requires_permission(): void
    {
        $exam = $this->publishedExam(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get("/staff/exams/{$exam->id}/invigilation")
            ->assertForbidden();
    }

    private function publishedExam(User $publisher): Exam
    {
        $exam = Exam::query()->create([
            'subject_id' => Subject::query()->firstOrCreate(['name' => 'Mathematics'])->id,
            'created_by' => $publisher->id,
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
        ]);
        $question = $this->question();
        $exam->draftSlots()->create([
            'slot_type' => 'fixed_question',
            'question_version_id' => $question->id,
            'question_count' => 1,
            'marks_per_question' => 1,
            'position' => 1,
        ]);
        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        return $exam->fresh();
    }

    private function question(): QuestionVersion
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
            'title' => 'Question?',
        ]);
        $question = QuestionVersion::query()->create([
            'question_bank_entry_id' => $entry->id,
            'version_number' => 1,
            'type' => QuestionType::SingleChoice,
            'difficulty' => 'easy',
            'question_text' => 'Question?',
            'default_marks' => 1,
            'negative_marks' => 0,
            'grading_rules' => [],
            'content_hash' => hash('sha256', uniqid()),
            'ready_at' => now(),
        ]);

        foreach (['A', 'B'] as $position => $text) {
            QuestionOption::query()->create([
                'question_version_id' => $question->id,
                'position' => $position + 1,
                'option_text' => $text,
                'fraction' => $position === 0 ? 1 : 0,
            ]);
        }

        return $question->load('options');
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $perm = Permission::query()->firstOrCreate(
            ['name' => $permission],
            ['label' => $permission, 'group' => 'test', 'description' => null]
        );
        $role = Role::query()->create(['name' => 'role-'.uniqid(), 'label' => 'Test']);
        $role->permissions()->attach($perm);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function studentFor(User $user, string $admission): Student
    {
        return Student::query()->create([
            'user_id' => $user->id,
            'admission_number' => $admission,
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);
    }
}
