<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
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
use App\Services\Attempts\AttemptStarter;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingQueueAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_grading_queue_shows_exams_with_pending_manual_grading(): void
    {
        $grader = $this->userWithPermission('grades.grade');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-701');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill(['status' => AttemptStatus::Submitted, 'submitted_at' => now()])->save();

        $response = $this->actingAs($grader)->get(route('staff.grading.index'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/Grading/Index')
            ->where('exams.0.id', $exam->id)
            ->where('exams.0.pending_count', 1)
        );
    }

    public function test_grading_queue_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.grading.index'))->assertForbidden();
    }

    public function test_grading_exam_page_shows_attempts(): void
    {
        $grader = $this->userWithPermission('grades.grade');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-702');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill(['status' => AttemptStatus::Submitted, 'submitted_at' => now()])->save();

        $response = $this->actingAs($grader)->get(route('staff.grading.exam', $exam));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/Grading/Exam')
            ->where('exam.id', $exam->id)
            ->has('attempts', 1)
            ->where('pendingCount', 1)
        );
    }

    public function test_grading_attempt_page_shows_questions(): void
    {
        $grader = $this->userWithPermission('grades.grade');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-703');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);

        $response = $this->actingAs($grader)->get(route('staff.grading.attempt', $attempt));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/Grading/Attempt')
            ->where('attempt_id', $attempt->id)
            ->has('questions', 1)
            ->where('student_name', 'Test Student')
        );
    }

    public function test_csv_export_downloads_results(): void
    {
        $staff = $this->userWithPermission('results.release');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-704');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill([
            'status' => AttemptStatus::Released,
            'score' => 80,
            'max_score' => 100,
            'percentage' => 80,
            'graded_at' => now(),
            'released_at' => now(),
            'submitted_at' => now(),
        ])->save();

        $response = $this->actingAs($staff)->get(route('staff.results.export', $exam));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="results-first-ca.csv"');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Admission Number', $content);
        $this->assertStringContainsString('STD-704', $content);
        $this->assertStringContainsString('80', $content);
    }

    public function test_export_requires_permission(): void
    {
        $exam = $this->publishedExam();
        $this->actingAs(User::factory()->create())->get(route('staff.results.export', $exam))->assertForbidden();
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

    private function studentWithEnrollment(User $user, string $admissionNumber): Student
    {
        $session = AcademicSession::query()->firstOrCreate(['name' => '2026/2027'], ['is_current' => true]);
        $classLevel = ClassLevel::query()->firstOrCreate(['name' => 'JSS 1']);
        $section = Section::query()->firstOrCreate([
            'class_level_id' => $classLevel->id,
            'name' => 'A',
        ]);
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
