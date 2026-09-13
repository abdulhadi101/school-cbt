<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Permission;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_question_author_can_create_question_with_options_and_tags(): void
    {
        $author = $this->userWithPermissions(['questions.view', 'questions.create']);
        [$subject, $level, $category] = $this->catalog();

        $this->actingAs($author)->post(route('staff.questions.store'), $this->payload($subject, $level, $category))
            ->assertRedirect();

        $entry = QuestionBankEntry::query()->with(['tags', 'latestVersion.options'])->sole();

        $this->assertSame('Simple addition', $entry->title);
        $this->assertSame('draft', $entry->status->value);
        $this->assertSame(['arithmetic', 'first term'], $entry->tags->pluck('name')->sort()->values()->all());
        $this->assertSame(1, $entry->latestVersion->version_number);
        $this->assertSame(2, $entry->latestVersion->options()->count());
        $this->assertSame('1.0000', $entry->latestVersion->options()->where('option_text', '4')->sole()->fraction);
    }

    public function test_question_bank_pages_require_question_permissions(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.questions.index'))->assertForbidden();
        $this->actingAs($this->userWithPermissions(['questions.view']))->get(route('staff.questions.index'))->assertOk();
    }

    public function test_reviewer_can_mark_question_ready(): void
    {
        $author = $this->userWithPermissions(['questions.create']);
        $reviewer = $this->userWithPermissions(['questions.review']);
        [$subject, $level, $category] = $this->catalog();
        $entry = $this->createQuestion($author, $subject, $level, $category);

        $this->actingAs($reviewer)->post(route('staff.questions.ready', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame('ready', $entry->status->value);
        $this->assertNotNull($entry->latestVersion->ready_at);
    }

    public function test_updating_ready_question_creates_new_draft_version_without_mutating_ready_version(): void
    {
        $author = $this->userWithPermissions(['questions.create', 'questions.update']);
        $reviewer = $this->userWithPermissions(['questions.review']);
        [$subject, $level, $category] = $this->catalog();
        $entry = $this->createQuestion($author, $subject, $level, $category);
        $this->actingAs($reviewer)->post(route('staff.questions.ready', $entry));
        $readyVersion = $entry->fresh()->latestVersion;

        $updated = $this->payload($subject, $level, $category, ['question_text' => 'What is 3 + 3?']);
        $this->actingAs($author)->put(route('staff.questions.update', $entry), $updated)->assertRedirect();

        $this->assertSame('What is 2 + 2?', $readyVersion->fresh()->question_text);
        $this->assertSame(2, $entry->versions()->count());
        $this->assertSame('draft', $entry->fresh()->status->value);
        $this->assertSame('What is 3 + 3?', $entry->fresh()->latestVersion->question_text);
        $this->assertNull($entry->fresh()->latestVersion->ready_at);
    }

    public function test_edit_page_contains_answer_keys_only_for_authorized_staff(): void
    {
        $author = $this->userWithPermissions(['questions.create', 'questions.view']);
        [$subject, $level, $category] = $this->catalog();
        $entry = $this->createQuestion($author, $subject, $level, $category);

        $response = $this->actingAs($author)->get(route('staff.questions.edit', $entry));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Staff/Questions/Form')
            ->where('entry.version.options.0.fraction', '1.0000')
        );

        $this->actingAs(User::factory()->create())->get(route('staff.questions.edit', $entry))->assertForbidden();
    }

    private function createQuestion(User $author, Subject $subject, ClassLevel $level, QuestionCategory $category): QuestionBankEntry
    {
        $this->actingAs($author)->post(route('staff.questions.store'), $this->payload($subject, $level, $category));

        return QuestionBankEntry::query()->sole();
    }

    private function payload(Subject $subject, ClassLevel $level, QuestionCategory $category, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Simple addition',
            'subject_id' => $subject->id,
            'class_level_id' => $level->id,
            'category_id' => $category->id,
            'tags' => 'arithmetic, first term',
            'type' => 'single_choice',
            'difficulty' => 'easy',
            'question_text' => 'What is 2 + 2?',
            'default_marks' => 1,
            'negative_marks' => 0,
            'grading_rules' => [],
            'general_feedback' => 'Review addition facts.',
            'explanation' => '2 + 2 equals 4.',
            'options' => [
                ['option_text' => '4', 'fraction' => 1, 'feedback' => 'Correct.'],
                ['option_text' => '5', 'fraction' => 0, 'feedback' => 'Check the sum again.'],
            ],
        ], $overrides);
    }

    private function catalog(): array
    {
        $subject = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH']);
        $level = ClassLevel::query()->create(['name' => 'JSS 1', 'sort_order' => 1]);
        $category = QuestionCategory::query()->create([
            'subject_id' => $subject->id,
            'class_level_id' => $level->id,
            'name' => 'Number Work',
            'slug' => 'number-work',
        ]);

        return [$subject, $level, $category];
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
