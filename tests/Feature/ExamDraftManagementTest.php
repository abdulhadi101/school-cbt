<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Permission;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_exam_author_can_create_draft_with_ready_question_slots(): void
    {
        $author = $this->userWithPermissions(['exams.view', 'exams.create']);
        [$subject, $term] = $this->catalog();
        $question = $this->createQuestionVersion($subject);

        $this->actingAs($author)->post(route('staff.exams.store'), $this->payload($subject, $term, $question))
            ->assertRedirect();

        $exam = Exam::query()->with('draftSlots')->sole();

        $this->assertSame('JSS 1 Mathematics First CA', $exam->title);
        $this->assertSame(ExamStatus::Draft, $exam->status);
        $this->assertSame('10.00', $exam->total_marks);
        $this->assertCount(1, $exam->draftSlots);
        $this->assertSame($question->id, $exam->draftSlots->first()->question_version_id);
    }

    public function test_exam_pages_require_exam_permissions(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.exams.index'))->assertForbidden();
        $this->actingAs($this->userWithPermissions(['exams.view']))->get(route('staff.exams.index'))->assertOk();
    }

    public function test_draft_slots_reject_non_ready_or_retired_question_versions(): void
    {
        $author = $this->userWithPermissions(['exams.create']);
        [$subject, $term] = $this->catalog();
        $question = $this->createQuestionVersion($subject, entryStatus: 'draft', ready: false);

        $this->actingAs($author)->post(route('staff.exams.store'), $this->payload($subject, $term, $question))
            ->assertSessionHasErrors('slots');

        $this->assertSame(0, Exam::query()->count());
    }

    public function test_author_can_submit_and_officer_can_approve_and_publish_draft(): void
    {
        $author = $this->userWithPermissions(['exams.create', 'exams.submit']);
        $officer = $this->userWithPermissions(['exams.approve', 'exams.publish']);
        [$subject, $term] = $this->catalog();
        $question = $this->createQuestionVersion($subject);
        $this->actingAs($author)->post(route('staff.exams.store'), $this->payload($subject, $term, $question));
        $exam = Exam::query()->sole();

        $this->actingAs($author)->post(route('staff.exams.submit', $exam))->assertRedirect();
        $this->actingAs($officer)->post(route('staff.exams.approve', $exam))->assertRedirect();
        $this->actingAs($officer)->post(route('staff.exams.draft_publish', $exam))->assertRedirect();

        $exam->refresh();

        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertSame(1, $exam->revisions()->count());
        $this->assertSame($question->id, $exam->latestRevision->slots()->sole()->question_version_id);
    }

    public function test_submit_rejects_slot_total_mismatches(): void
    {
        $author = $this->userWithPermissions(['exams.create', 'exams.submit']);
        [$subject, $term] = $this->catalog();
        $question = $this->createQuestionVersion($subject);
        $this->actingAs($author)->post(route('staff.exams.store'), $this->payload($subject, $term, $question, ['total_marks' => 15]));
        $exam = Exam::query()->sole();

        $this->actingAs($author)->post(route('staff.exams.submit', $exam))->assertSessionHasErrors('exam');

        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    private function payload(Subject $subject, Term $term, QuestionVersion $question, array $overrides = []): array
    {
        return array_merge([
            'title' => 'JSS 1 Mathematics First CA',
            'description' => 'First continuous assessment.',
            'instructions' => 'Answer all questions.',
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'exam_type' => 'ca',
            'duration_minutes' => 30,
            'total_marks' => 10,
            'pass_percentage' => 50,
            'max_attempts' => 1,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'score_release_policy' => 'manual',
            'show_responses' => false,
            'show_correct_answers' => false,
            'show_feedback' => false,
            'slots' => [
                ['question_version_id' => $question->id, 'marks_per_question' => 10],
            ],
        ], $overrides);
    }

    private function catalog(): array
    {
        $subject = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH']);
        $session = AcademicSession::query()->create(['name' => '2026/2027', 'is_current' => true]);
        $term = Term::query()->create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'is_current' => true,
        ]);

        return [$subject, $term];
    }

    private function createQuestionVersion(Subject $subject, string $entryStatus = 'ready', bool $ready = true): QuestionVersion
    {
        $classLevel = ClassLevel::query()->create(['name' => 'JSS 1', 'sort_order' => 1]);
        $category = QuestionCategory::query()->create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'name' => 'Number Work',
            'slug' => 'number-work-'.uniqid(),
        ]);
        $entry = QuestionBankEntry::query()->create([
            'category_id' => $category->id,
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'status' => $entryStatus,
            'title' => 'Simple addition',
        ]);
        $question = QuestionVersion::query()->create([
            'question_bank_entry_id' => $entry->id,
            'version_number' => 1,
            'type' => QuestionType::SingleChoice,
            'difficulty' => 'easy',
            'question_text' => 'What is 2 + 2?',
            'default_marks' => 1,
            'negative_marks' => 0,
            'grading_rules' => [],
            'content_hash' => hash('sha256', 'What is 2 + 2?'),
            'ready_at' => $ready ? now() : null,
        ]);

        foreach (['4', '5'] as $position => $optionText) {
            QuestionOption::query()->create([
                'question_version_id' => $question->id,
                'position' => $position + 1,
                'option_text' => $optionText,
                'fraction' => $position === 0 ? 1 : 0,
            ]);
        }

        return $question;
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
