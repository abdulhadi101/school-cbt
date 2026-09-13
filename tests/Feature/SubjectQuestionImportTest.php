<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Permission;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\Role;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_staff_sees_only_assigned_subjects(): void
    {
        [$subject, $level] = $this->catalog('Mathematics', 'MTH', 'JSS 1');
        $this->catalog('English', 'ENG', 'JSS 1');
        $staff = $this->assignedStaff($subject, $level);

        $response = $this->actingAs($staff)->get(route('staff.my-subjects.index'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/MySubjects/Index')
            ->where('items.0.subject.name', 'Mathematics')
        );

        $items = $response->viewData('page')['props']['items'];
        $this->assertSame(['Mathematics'], collect($items)->pluck('subject.name')->all());
    }

    public function test_unassigned_staff_cannot_open_context(): void
    {
        [$subject, $level] = $this->catalog();
        $stranger = $this->userWithPermissions(['questions.view', 'questions.create']);

        $this->actingAs($stranger)->get(route('staff.subject-questions.index', [$subject, $level]))->assertForbidden();
        $this->actingAs($stranger)->get(route('staff.subject-questions.import', [$subject, $level]))->assertForbidden();
    }

    public function test_assigned_staff_can_import_aiken_with_locked_context(): void
    {
        [$subject, $level] = $this->catalog();
        $staff = $this->assignedStaff($subject, $level);

        $content = "Q1. What is a pawpaw?\nA. a place\nB. a town\nC. a fruit\nD. All of the above\nANSWER: C\n\nQ2. What is 2 + 2?\nA. 3\nB. 4\nANSWER: B";

        $this->actingAs($staff)->post(route('staff.subject-questions.store', [$subject, $level]), [
            'format' => 'aiken',
            'content' => $content,
            'default_marks' => 2,
            'difficulty' => 'easy',
            'tags' => 'first term',
        ])->assertRedirect(route('staff.subject-questions.index', [$subject, $level]));

        $entries = QuestionBankEntry::query()->with('latestVersion.options')->get();

        $this->assertSame(2, $entries->count());
        $this->assertTrue($entries->every(fn ($e): bool => (int) $e->subject_id === $subject->id && (int) $e->class_level_id === $level->id));
        $this->assertSame('1.0000', $entries->firstWhere('latestVersion.question_text', 'What is a pawpaw?')->latestVersion->options->firstWhere('option_text', 'a fruit')->fraction);
        $this->assertSame('2.00', $entries->first()->latestVersion->default_marks);
    }

    public function test_import_rejects_content_without_valid_questions(): void
    {
        [$subject, $level] = $this->catalog();
        $staff = $this->assignedStaff($subject, $level);

        $this->actingAs($staff)->post(route('staff.subject-questions.store', [$subject, $level]), [
            'format' => 'aiken',
            'content' => "No options here\nA. only one",
            'default_marks' => 1,
        ])->assertSessionHasErrors('content');

        $this->assertSame(0, QuestionBankEntry::query()->count());
    }

    public function test_preview_shows_valid_questions_and_errors_without_saving(): void
    {
        [$subject, $level] = $this->catalog();
        $staff = $this->assignedStaff($subject, $level);

        $content = "Q1. What is 2 + 2?\nA. 3\nB. 4\nANSWER: B\n\nBroken block without answer\nA. x\nB. y";

        $this->actingAs($staff)->post(route('staff.subject-questions.preview', [$subject, $level]), [
            'format' => 'aiken',
            'content' => $content,
            'default_marks' => 1,
            'difficulty' => 'medium',
            'tags' => '',
        ])->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/SubjectQuestions/Import')
            ->where('preview.questions.0.question_text', 'What is 2 + 2?')
            ->where('preview.questions.0.type', 'single_choice')
            ->where('preview.errors.0.block', 2)
        );

        $this->assertSame(0, QuestionBankEntry::query()->count());
    }

    public function test_gift_import_creates_multiple_choice_with_split_fractions(): void
    {
        [$subject, $level] = $this->catalog();
        $staff = $this->assignedStaff($subject, $level);

        $content = "Which are even numbers? {\n= 2\n= 4\n~ 3\n}";

        $this->actingAs($staff)->post(route('staff.subject-questions.store', [$subject, $level]), [
            'format' => 'gift',
            'content' => $content,
            'default_marks' => 1,
        ])->assertRedirect();

        $entry = QuestionBankEntry::query()->with('latestVersion.options')->sole();

        $this->assertSame('multiple_choice', $entry->latestVersion->type->value);
        $this->assertSame(['0.5000', '0.5000', '0.0000'], $entry->latestVersion->options->sortBy('position')->pluck('fraction')->all());
    }

    public function test_import_preserves_latex_verbatim(): void
    {
        [$subject, $level] = $this->catalog();
        $staff = $this->assignedStaff($subject, $level);

        $content = "Q1. Solve \\(x^2 = 4\\) for x.\nA. \\(x = 2\\)\nB. \\(x = -2\\)\nC. \\(x = \\pm 2\\)\nD. \\(x = 0\\)\nANSWER: C";

        $this->actingAs($staff)->post(route('staff.subject-questions.store', [$subject, $level]), [
            'format' => 'aiken',
            'content' => $content,
            'default_marks' => 1,
        ])->assertRedirect();

        $entry = QuestionBankEntry::query()->with('latestVersion.options')->sole();

        $this->assertSame('Solve \\(x^2 = 4\\) for x.', $entry->latestVersion->question_text);
        $this->assertSame('\\(x = \\pm 2\\)', $entry->latestVersion->options->firstWhere('fraction', '1.0000')->option_text);
    }

    public function test_gift_numerical_import_stores_grading_rules(): void
    {
        [$subject, $level] = $this->catalog();
        $staff = $this->assignedStaff($subject, $level);

        $this->actingAs($staff)->post(route('staff.subject-questions.store', [$subject, $level]), [
            'format' => 'gift',
            'content' => 'What is pi? {#3.14:0.01}',
            'default_marks' => 1,
        ])->assertRedirect();

        $entry = QuestionBankEntry::query()->with('latestVersion')->sole();

        $this->assertSame('numerical', $entry->latestVersion->type->value);
        $this->assertSame(3.14, $entry->latestVersion->grading_rules['answer']);
        $this->assertSame(0.01, $entry->latestVersion->grading_rules['tolerance']);
        $this->assertSame(0, $entry->latestVersion->options()->count());
    }

    /**
     * @return array{0: Subject, 1: ClassLevel}
     */
    private function catalog(string $name = 'Mathematics', string $code = 'MTH', string $levelName = 'JSS 1', bool $withCategory = true): array
    {
        $subject = Subject::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        $level = ClassLevel::query()->firstOrCreate(['name' => $levelName], ['sort_order' => 1]);

        if ($withCategory) {
            QuestionCategory::query()->firstOrCreate(
                ['subject_id' => $subject->id, 'class_level_id' => $level->id, 'slug' => 'slug-'.$subject->id.'-'.$level->id],
                ['name' => $name.' '.$levelName.' General']
            );
        }

        return [$subject, $level];
    }

    private function assignedStaff(Subject $subject, ClassLevel $level): User
    {
        $user = $this->userWithPermissions(['questions.view', 'questions.create', 'questions.update']);
        $profile = StaffProfile::query()->create([
            'user_id' => $user->id,
            'staff_number' => 'STF-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Teacher',
        ]);
        $section = Section::query()->create(['class_level_id' => $level->id, 'name' => 'A'.uniqid()]);
        TeachingAssignment::query()->create([
            'staff_profile_id' => $profile->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'class_level_id' => $level->id,
        ]);

        return $user->fresh();
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
