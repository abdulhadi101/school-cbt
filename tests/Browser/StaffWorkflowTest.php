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

test('staff can access exams page', function (): void {
    $user = User::create([
        'name' => 'Staff User', 'email' => 'staff@example.com',
        'password' => Hash::make('password'), 'email_verified_at' => now(),
    ]);
    $role = Role::where('name', 'exam-officer')->first();
    $user->roles()->attach($role);

    visit('/login')
        ->fill('email', 'staff@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    visit('/staff/exams')
        ->assertSee('Exams')
        ->assertNoJavaScriptErrors();
});

test('student can access exams page', function (): void {
    $user = User::create([
        'name' => 'Student User', 'email' => 'student@example.com',
        'password' => Hash::make('password'), 'email_verified_at' => now(),
    ]);
    $role = Role::where('name', 'student')->first();
    $user->roles()->attach($role);

    // Student needs a Student model profile
    \App\Models\Student::create([
        'user_id' => $user->id,
        'admission_number' => 'STD-TEST-001',
        'first_name' => 'Student',
        'last_name' => 'User',
    ]);

    visit('/login')
        ->fill('email', 'student@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    visit('/student/exams')
        ->assertSee('Exams')
        ->assertNoJavaScriptErrors();
});

test('staff can access grading page', function (): void {
    $user = User::create([
        'name' => 'Grader User', 'email' => 'grader@example.com',
        'password' => Hash::make('password'), 'email_verified_at' => now(),
    ]);
    $role = Role::where('name', 'grader')->first();
    $user->roles()->attach($role);

    visit('/login')
        ->fill('email', 'grader@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    visit('/staff/grading')
        ->assertSee('Grading')
        ->assertNoJavaScriptErrors();
});

test('staff can access questions page', function (): void {
    $user = User::create([
        'name' => 'Question Master', 'email' => 'questions@example.com',
        'password' => Hash::make('password'), 'email_verified_at' => now(),
    ]);
    $role = Role::where('name', 'question-author')->first();
    $user->roles()->attach($role);

    visit('/login')
        ->fill('email', 'questions@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    visit('/staff/questions')
        ->assertSee('Questions')
        ->assertNoJavaScriptErrors();
});
