<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(PermissionRegistry::all())
            ->mapWithKeys(fn (array $permission) => [
                $permission['name'] => Permission::query()->updateOrCreate(
                    ['name' => $permission['name']],
                    [
                        'label' => $permission['label'],
                        'group' => $permission['group'],
                        'description' => $permission['description'],
                    ]
                ),
            ]);

        $labels = [
            'system-admin' => 'System Administrator',
            'exam-officer' => 'Exam Officer',
            'question-author' => 'Question Author',
            'invigilator' => 'Invigilator',
            'grader' => 'Grader',
            'report-viewer' => 'Report Viewer',
            'student' => 'Student',
        ];

        foreach (PermissionRegistry::rolePermissions() as $roleName => $permissionNames) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName],
                [
                    'label' => $labels[$roleName],
                    'description' => null,
                ]
            );

            $role->permissions()->sync(
                $permissions->only($permissionNames)->pluck('id')->all()
            );
        }
    }
}
