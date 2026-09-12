<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Permission;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_staff_can_assign_exam_audiences_and_accommodations(): void
    {
        $staff = $this->userWithPermission('exams.update');
        [$classLevel, $section] = $this->classAndSection('JSS 1', 'A');
        $student = $this->studentWithEnrollment($this->userWithPermission('attempts.take'), 'STD-501', $section);
        $exam = $this->publishedExam();

        $this->actingAs($staff)->put(route('staff.exams.schedule.update', $exam), [
            'opens_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'audiences' => [
                ['class_level_id' => $classLevel->id, 'section_id' => $section->id],
            ],
            'accommodations' => [
                [
                    'student_id' => $student->id,
                    'extra_minutes' => 15,
                    'extra_attempts' => 1,
                    'reason' => 'Approved support',
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $exam->audiences()->count());
        $this->assertSame(1, $exam->accommodations()->count());
        $this->assertSame(15, $exam->accommodations()->sole()->extra_minutes);
    }

    public function test_student_exam_list_only_shows_assigned_or_accommodated_exams(): void
    {
        [, $sectionA] = $this->classAndSection('JSS 1', 'A');
        [$otherClass, $sectionB] = $this->classAndSection('JSS 2', 'A');
        $user = $this->userWithPermission('attempts.take');
        $this->studentWithEnrollment($user, 'STD-502', $sectionA);
        $assigned = $this->publishedExam(['title' => 'Assigned CA']);
        $hidden = $this->publishedExam(['title' => 'Hidden CA']);
        $assigned->audiences()->create(['class_level_id' => $sectionA->class_level_id, 'section_id' => $sectionA->id]);
        $hidden->audiences()->create(['class_level_id' => $otherClass->id, 'section_id' => $sectionB->id]);

        $response = $this->actingAs($user)->get(route('student.exams.index'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Student/Exams/Index')
            ->where('exams.0.title', 'Assigned CA')
            ->missing('exams.1')
        );
    }

    public function test_attempt_start_rejects_students_outside_assigned_audience(): void
    {
        [$classLevel, $sectionA] = $this->classAndSection('JSS 1', 'A');
        [, $sectionB] = $this->classAndSection('JSS 2', 'A');
        $user = $this->userWithPermission('attempts.take');
        $this->studentWithEnrollment($user, 'STD-503', $sectionB);
        $exam = $this->publishedExam();
        $exam->audiences()->create(['class_level_id' => $classLevel->id, 'section_id' => $sectionA->id]);

        $this->actingAs($user)->postJson(route('attempts.start'), ['exam_id' => $exam->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This exam is not assigned to your class or section.');
    }

    public function test_attempt_start_enforces_schedule_window(): void
    {
        [, $section] = $this->classAndSection('JSS 1', 'A');
        $user = $this->userWithPermission('attempts.take');
        $this->studentWithEnrollment($user, 'STD-504', $section);
        $exam = $this->publishedExam([
            'opens_at' => now()->addHour(),
            'closes_at' => now()->addHours(2),
        ]);
        $exam->audiences()->create(['class_level_id' => $section->class_level_id, 'section_id' => $section->id]);

        $this->actingAs($user)->postJson(route('attempts.start'), ['exam_id' => $exam->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This exam has not opened yet.');
    }

    public function test_accommodation_can_override_audience_attempt_limit_and_duration(): void
    {
        [, $section] = $this->classAndSection('JSS 1', 'A');
        [$otherClass, $otherSection] = $this->classAndSection('JSS 2', 'A');
        $user = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($user, 'STD-505', $section);
        $exam = $this->publishedExam(['duration_minutes' => 30, 'max_attempts' => 1]);
        $exam->audiences()->create(['class_level_id' => $otherClass->id, 'section_id' => $otherSection->id]);
        $exam->accommodations()->create([
            'student_id' => $student->id,
            'extra_minutes' => 20,
            'extra_attempts' => 1,
            'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('attempts.start'), ['exam_id' => $exam->id]);

        $response->assertOk();
        $attempt = $student->attempts()->sole();
        $this->assertEqualsWithDelta(50 * 60, now()->diffInSeconds($attempt->deadline_at), 5);
    }

    private function publishedExam(array $overrides = []): Exam
    {
        $publisher = User::factory()->create();
        $subject = Subject::query()->firstOrCreate(['name' => 'Mathematics']);
        $exam = Exam::query()->create(array_merge([
            'subject_id' => $subject->id,
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
        ], $overrides));
        $question = $this->question($subject);
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

    private function question(Subject $subject): QuestionVersion
    {
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

        foreach (['A', 'B'] as $position => $optionText) {
            QuestionOption::query()->create([
                'question_version_id' => $question->id,
                'position' => $position + 1,
                'option_text' => $optionText,
                'fraction' => $position === 0 ? 1 : 0,
            ]);
        }

        return $question->load('options');
    }

    private function classAndSection(string $className, string $sectionName): array
    {
        $classLevel = ClassLevel::query()->firstOrCreate(['name' => $className]);
        $section = Section::query()->firstOrCreate([
            'class_level_id' => $classLevel->id,
            'name' => $sectionName,
        ]);

        return [$classLevel, $section];
    }

    private function studentWithEnrollment(User $user, string $admissionNumber, Section $section): Student
    {
        $session = AcademicSession::query()->firstOrCreate(['name' => '2026/2027'], ['is_current' => true]);
        $student = Student::query()->create([
            'user_id' => $user->id,
            'admission_number' => $admissionNumber,
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'section_id' => $section->id,
            'is_current' => true,
        ]);

        return $student;
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permission],
            ['label' => $permission, 'group' => 'test', 'description' => null]
        );
        $role = Role::query()->create(['name' => 'role-'.uniqid(), 'label' => 'Test Role']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
