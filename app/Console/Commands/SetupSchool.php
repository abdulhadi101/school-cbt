<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetupSchool extends Command
{
    protected $signature = 'school:setup
        {--name=Pilot School : School name}
        {--admin-name=System Administrator : Initial admin display name}
        {--admin-email=admin@school.test : Initial admin email}
        {--admin-password= : Initial admin password. Generated if omitted}';

    protected $description = 'Create the first school profile, admin user, and pilot academic defaults';

    public function handle(): int
    {
        $password = (string) ($this->option('admin-password') ?: Str::password(16));

        SchoolSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => (string) $this->option('name'),
                'code' => 'default',
                'settings' => [
                    'timezone' => config('app.timezone'),
                    'pilot_capacity' => 120,
                ],
            ]
        );

        $admin = User::query()->updateOrCreate(
            ['email' => (string) $this->option('admin-email')],
            [
                'name' => (string) $this->option('admin-name'),
                'username' => 'admin',
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );

        if ($role = Role::query()->where('name', 'system-admin')->first()) {
            $admin->roles()->syncWithoutDetaching([$role->id]);
        }

        $session = AcademicSession::query()->updateOrCreate(
            ['name' => now()->year.'/'.now()->addYear()->year],
            ['is_current' => true]
        );

        foreach (['First Term', 'Second Term', 'Third Term'] as $index => $termName) {
            Term::query()->updateOrCreate(
                ['academic_session_id' => $session->id, 'name' => $termName],
                ['is_current' => $index === 0]
            );
        }

        foreach (['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3'] as $index => $levelName) {
            $level = ClassLevel::query()->updateOrCreate(
                ['name' => $levelName],
                ['sort_order' => $index + 1]
            );

            Section::query()->updateOrCreate(
                ['class_level_id' => $level->id, 'name' => 'A'],
                ['capacity' => 40]
            );
        }

        foreach (['Mathematics', 'English Language', 'Basic Science', 'Civic Education'] as $subjectName) {
            Subject::query()->firstOrCreate(['name' => $subjectName]);
        }

        $this->info('School setup completed.');

        if (! $this->option('admin-password')) {
            $this->warn('Generated admin password: '.$password);
        }

        return self::SUCCESS;
    }
}
