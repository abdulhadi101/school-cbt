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

class DashboardAndResultReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_staff_dashboard_shows_stats_and_recent_attempts(): void
    {
        $staff = $this->userWithPermission('exams.view');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-601');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill(['status' => AttemptStatus::Submitted, 'submitted_at' => now()])->save();

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.published_exams')
            ->has('stats.total_attempts')
            ->has('stats.pending_grading')
            ->has('stats.released_results')
            ->has('recentAttempts')
            ->has('examsNeedingGrading')
        );
    }

    public function test_student_dashboard_shows_stats(): void
    {
        $user = $this->userWithPermission('attempts.take');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.total_attempts')
            ->has('stats.released_results')
            ->has('stats.average_score')
        );
    }

    public function test_result_release_page_shows_exam_attempts(): void
    {
        $staff = $this->userWithPermission('results.release');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-602');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill([
            'status' => AttemptStatus::Graded,
            'score' => 80,
            'max_score' => 100,
            'percentage' => 80,
            'graded_at' => now(),
        ])->save();

        $response = $this->actingAs($staff)->get(route('staff.results.show', $exam));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/Results/Show')
            ->where('exam.id', $exam->id)
            ->has('attempts', 1)
            ->where('summary.total', 1)
            ->where('summary.graded', 1)
            ->where('summary.released', 0)
        );
    }

    public function test_result_release_page_requires_permission(): void
    {
        $exam = $this->publishedExam();
        $this->actingAs(User::factory()->create())->get(route('staff.results.show', $exam))->assertForbidden();
    }

    public function test_single_attempt_release(): void
    {
        $staff = $this->userWithPermission('results.release');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-603');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill([
            'status' => AttemptStatus::Graded,
            'score' => 90,
            'max_score' => 100,
            'percentage' => 90,
            'graded_at' => now(),
        ])->save();

        $response = $this->actingAs($staff)->postJson(route('staff.results.release', $exam), [
            'attempt_id' => $attempt->id,
        ]);

        $response->assertOk()->assertJsonPath('status', 'released');
        $this->assertSame(AttemptStatus::Released, $attempt->fresh()->status);
    }

    public function test_bulk_release_all_graded_attempts(): void
    {
        $staff = $this->userWithPermission('results.release');
        $exam = $this->publishedExam();

        foreach (range(1, 3) as $i) {
            $studentUser = $this->userWithPermission('attempts.take');
            $student = $this->studentWithEnrollment($studentUser, "STD-61$i");
            $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
            $attempt->forceFill([
                'status' => AttemptStatus::Graded,
                'score' => 70 + $i,
                'max_score' => 100,
                'percentage' => 70 + $i,
                'graded_at' => now(),
            ])->save();
        }

        $response = $this->actingAs($staff)->postJson(route('staff.results.release-all', $exam));

        $response->assertOk()->assertJsonPath('released_count', 3);
        $this->assertSame(0, $exam->attempts()->where('status', AttemptStatus::Graded)->count());
        $this->assertSame(3, $exam->attempts()->where('status', AttemptStatus::Released)->count());
    }

    public function test_releasing_non_graded_attempt_fails(): void
    {
        $staff = $this->userWithPermission('results.release');
        $exam = $this->publishedExam();
        $studentUser = $this->userWithPermission('attempts.take');
        $student = $this->studentWithEnrollment($studentUser, 'STD-604');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);

        $response = $this->actingAs($staff)->postJson(route('staff.results.release', $exam), [
            'attempt_id' => $attempt->id,
        ]);

        $response->assertUnprocessable()->assertJsonPath('message', 'Only fully graded attempts can be released.');
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
