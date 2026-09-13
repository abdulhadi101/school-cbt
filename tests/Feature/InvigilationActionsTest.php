<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\AuditLog;
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
use App\Services\Attempts\AttemptExpirer;
use App\Services\Attempts\AttemptStarter;
use App\Services\Attempts\AttemptSubmitter;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InvigilationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_hall_lists_all_live_exams_across_subjects(): void
    {
        $publisher = User::factory()->create();
        $liveA = $this->publishedExam($publisher, ['title' => 'JSS 1 Maths', 'opens_at' => now()->subHour(), 'closes_at' => now()->addHour()]);
        $liveB = $this->publishedExam($publisher, ['title' => 'SS 2 English', 'subject_name' => 'English Language', 'opens_at' => now()->subHour(), 'closes_at' => now()->addHour()]);
        $this->publishedExam($publisher, ['title' => 'Closed History', 'opens_at' => now()->subHours(3), 'closes_at' => now()->subHours(2)]);

        $student = $this->student('STD-401');
        app(AttemptStarter::class)->startOrResume($liveB->fresh(), $student);

        $invigilator = $this->userWithPermissions(['attempts.invigilate']);

        $response = $this->actingAs($invigilator)->get(route('staff.invigilation.index'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/Invigilation/Index')
            ->where('exams.0.title', 'SS 2 English')
            ->where('exams.1.title', 'JSS 1 Maths')
        );

        $titles = collect($response->viewData('page')['props']['exams'])->pluck('title')->all();
        $this->assertNotContains('Closed History', $titles);
    }

    public function test_hall_requires_invigilation_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.invigilation.index'))->assertForbidden();
    }

    public function test_invigilator_can_extend_in_progress_attempt(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-402'));
        $oldDeadline = $attempt->deadline_at->copy();
        $staff = $this->userWithPermissions(['attempts.invigilate', 'attempts.reopen']);

        $this->actingAs($staff)->post(route('staff.attempts.extend', $attempt), [
            'extra_minutes' => 15,
            'reason' => 'Network outage in hall A.',
        ])->assertRedirect();

        $fresh = $attempt->fresh();
        $this->assertEquals($oldDeadline->addMinutes(15)->timestamp, $fresh->deadline_at->timestamp);
        $this->assertTrue($fresh->events()->where('event_type', 'attempt_extended')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'attempt.extended')->exists());
    }

    public function test_extend_requires_reason_and_reopen_permission(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-403'));

        $this->actingAs($this->userWithPermissions(['attempts.invigilate', 'attempts.reopen']))
            ->post(route('staff.attempts.extend', $attempt), ['extra_minutes' => 10])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->userWithPermissions(['attempts.invigilate']))
            ->post(route('staff.attempts.extend', $attempt), ['extra_minutes' => 10, 'reason' => 'x'])
            ->assertForbidden();
    }

    public function test_invigilator_can_reopen_submitted_attempt(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $student = $this->student('STD-404');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        app(AttemptSubmitter::class)->submit($attempt);
        $this->assertNotSame(AttemptStatus::InProgress, $attempt->fresh()->status);

        $staff = $this->userWithPermissions(['attempts.invigilate', 'attempts.reopen']);

        $this->actingAs($staff)->post(route('staff.attempts.reopen', $attempt), [
            'reason' => 'Submitted early by mistake.',
        ])->assertRedirect();

        $fresh = $attempt->fresh();
        $this->assertSame(AttemptStatus::InProgress, $fresh->status);
        $this->assertTrue($fresh->deadline_at->isFuture());
        $this->assertTrue($fresh->events()->where('event_type', 'attempt_reopened')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'attempt.reopened')->exists());

        $resumed = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $this->assertSame($attempt->id, $resumed->id);
    }

    public function test_reopen_rejects_in_progress_attempt(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-405'));

        $this->actingAs($this->userWithPermissions(['attempts.invigilate', 'attempts.reopen']))
            ->post(route('staff.attempts.reopen', $attempt), ['reason' => 'x'])
            ->assertSessionHasErrors('attempt');
    }

    public function test_invigilator_can_invalidate_attempt_with_reason(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-406'));
        $staff = $this->userWithPermissions(['attempts.invigilate', 'attempts.invalidate']);

        $this->actingAs($staff)->post(route('staff.attempts.invalidate', $attempt), [
            'reason' => 'Phone found during search.',
        ])->assertRedirect();

        $fresh = $attempt->fresh();
        $this->assertSame(AttemptStatus::Invalidated, $fresh->status);
        $this->assertSame('Phone found during search.', $fresh->invalidation_reason);
        $this->assertSame($staff->id, (int) $fresh->invalidated_by);
        $this->assertTrue($fresh->events()->where('event_type', 'attempt_invalidated')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'attempt.invalidated')->exists());
    }

    public function test_invalidate_requires_permission(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-407'));

        $this->actingAs($this->userWithPermissions(['attempts.invigilate']))
            ->post(route('staff.attempts.invalidate', $attempt), ['reason' => 'x'])
            ->assertForbidden();
    }

    public function test_board_flags_idle_attempts_and_action_availability(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $student = $this->student('STD-408');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
        $attempt->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();

        $this->actingAs($this->userWithPermissions(['attempts.invigilate']))
            ->get(route('staff.exams.invigilation', $exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Staff/Invigilation/Show')
                ->where('dashboard.attempts.0.is_idle', true)
                ->where('dashboard.attempts.0.can_extend', true)
                ->where('dashboard.attempts.0.can_reopen', false)
                ->where('dashboard.attempts.0.can_invalidate', true)
            );
    }

    public function test_extend_uses_now_when_deadline_already_passed(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-409'));
        $attempt->forceFill(['deadline_at' => now()->subMinutes(5)])->save();

        $this->actingAs($this->userWithPermissions(['attempts.invigilate', 'attempts.reopen']))
            ->post(route('staff.attempts.extend', $attempt), [
                'extra_minutes' => 10,
                'reason' => 'Late start, clock already ran out.',
            ])->assertRedirect();

        $deadline = $attempt->fresh()->deadline_at;
        $this->assertTrue($deadline->isFuture());
        $this->assertTrue($deadline->lessThanOrEqualTo(now()->addMinutes(11)));
    }

    public function test_invigilator_can_reopen_expired_attempt(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-410'));
        $attempt->forceFill(['deadline_at' => now()->subMinute()])->save();
        app(AttemptExpirer::class)->expireDue();
        $this->assertSame(AttemptStatus::Expired, $attempt->fresh()->status);

        $this->actingAs($this->userWithPermissions(['attempts.invigilate', 'attempts.reopen']))
            ->post(route('staff.attempts.reopen', $attempt), ['reason' => 'Hall power cut.'])
            ->assertRedirect();

        $this->assertSame(AttemptStatus::InProgress, $attempt->fresh()->status);
    }

    public function test_invalidated_attempt_blocks_new_start_at_max_attempts(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher);
        $student = $this->student('STD-411');
        $attempt = app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);

        $this->actingAs($this->userWithPermissions(['attempts.invigilate', 'attempts.invalidate']))
            ->post(route('staff.attempts.invalidate', $attempt), ['reason' => 'Malpractice.'])
            ->assertRedirect();

        $this->expectException(RuntimeException::class);
        app(AttemptStarter::class)->startOrResume($exam->fresh(), $student);
    }

    public function test_hall_includes_closed_window_exam_with_live_attempt(): void
    {
        $publisher = User::factory()->create();
        $exam = $this->publishedExam($publisher, ['title' => 'Closing CA', 'opens_at' => now()->subHour(), 'closes_at' => now()->addHour()]);
        app(AttemptStarter::class)->startOrResume($exam->fresh(), $this->student('STD-412'));
        $exam->forceFill(['closes_at' => now()->subMinute()])->save();

        $response = $this->actingAs($this->userWithPermissions(['attempts.invigilate']))
            ->get(route('staff.invigilation.index'));

        $response->assertOk();
        $titles = collect($response->viewData('page')['props']['exams'])->pluck('title')->all();
        $this->assertContains('Closing CA', $titles);
    }

    private function publishedExam(User $publisher, array $overrides = []): Exam
    {
        $subjectName = $overrides['subject_name'] ?? 'Mathematics';
        unset($overrides['subject_name']);
        $exam = Exam::query()->create(array_merge([
            'subject_id' => Subject::query()->firstOrCreate(['name' => $subjectName])->id,
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

    private function student(string $admission): Student
    {
        return Student::query()->create([
            'admission_number' => $admission,
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'role-'.uniqid(), 'label' => 'Test Role']);

        foreach ($permissions as $permission) {
            $perm = Permission::query()->firstOrCreate(
                ['name' => $permission],
                ['label' => $permission, 'group' => 'test', 'description' => null]
            );
            $role->permissions()->attach($perm);
        }

        $user->roles()->attach($role);

        return $user->fresh();
    }
}
