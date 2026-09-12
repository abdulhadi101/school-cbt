<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Support\Facades\Hash;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $permissions = collect(PermissionRegistry::all())
        ->mapWithKeys(fn (array $permission) => [
            $permission['name'] => Permission::query()->updateOrCreate(
                ['name' => $permission['name']],
                ['label' => $permission['label'], 'group' => $permission['group'], 'description' => $permission['description']]
            ),
        ]);

    $labels = [
        'system-admin' => 'System Administrator', 'exam-officer' => 'Exam Officer',
        'question-author' => 'Question Author', 'invigilator' => 'Invigilator',
        'grader' => 'Grader', 'report-viewer' => 'Report Viewer', 'student' => 'Student',
    ];

    foreach (PermissionRegistry::rolePermissions() as $roleName => $permissionNames) {
        $role = Role::query()->updateOrCreate(['name' => $roleName], ['label' => $labels[$roleName]]);
        $role->permissions()->sync($permissions->only($permissionNames)->pluck('id')->all());
    }
});

test('staff can access create exam page', function (): void {
    $user = User::create([
        'name' => 'Exam Creator', 'email' => 'creator@example.com',
        'password' => Hash::make('password'), 'email_verified_at' => now(),
    ]);
    $role = Role::where('name', 'question-author')->first();
    $user->roles()->attach($role);

    visit('/login')
        ->fill('email', 'creator@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    visit('/staff/exams/create')
        ->assertSee('Title')
        ->assertSee('Description')
        ->assertNoJavaScriptErrors();
});

test('staff can fill exam creation form', function (): void {
    $user = User::create([
        'name' => 'Form Filler', 'email' => 'filler@example.com',
        'password' => Hash::make('password'), 'email_verified_at' => now(),
    ]);
    $role = Role::where('name', 'question-author')->first();
    $user->roles()->attach($role);

    visit('/login')
        ->fill('email', 'filler@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    visit('/staff/exams/create')
        ->assertSee('New Exam Draft')
        ->assertSee('Save Draft')
        ->assertSee('Question Slots')
        ->type('Title', 'Midterm Mathematics')
        ->type('Description', 'Chapter 1-5 review')
        ->wait(1)
        ->assertNoJavaScriptErrors();
});
