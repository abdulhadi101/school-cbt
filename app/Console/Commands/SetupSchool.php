<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\QuestionCategory;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SetupSchool extends Command
{
    protected $signature = 'school:setup
        {--name=Pilot School : School name}
        {--admin-name=System Administrator : Initial admin display name}
        {--admin-email=admin@school.test : Initial admin email}
        {--admin-password= : Initial admin password. Generated if omitted}
        {--staff-password=password : Initial password for starter staff accounts}
        {--sample-roster : Write a sample roster CSV to local storage}
        {--sample-roster-path=rosters/sample-students.csv : Local disk path for the sample roster CSV}';

    protected $description = 'Create the first school profile, admin user, and pilot academic defaults';

    public function handle(): int
    {
        app(RolePermissionSeeder::class)->run();

        $password = (string) ($this->option('admin-password') ?: Str::password(16));
        $staffPassword = (string) $this->option('staff-password');

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

        $sessionName = now()->year.'/'.now()->addYear()->year;
        AcademicSession::query()->where('name', '!=', $sessionName)->update(['is_current' => false]);

        $session = AcademicSession::query()->updateOrCreate(
            ['name' => $sessionName],
            ['is_current' => true]
        );

        foreach (['First Term', 'Second Term', 'Third Term'] as $index => $termName) {
            if ($index === 0) {
                Term::query()->where('academic_session_id', $session->id)->where('name', '!=', $termName)->update(['is_current' => false]);
            }

            Term::query()->updateOrCreate(
                ['academic_session_id' => $session->id, 'name' => $termName],
                ['is_current' => $index === 0]
            );
        }

        $classLevels = [];
        $sections = [];
        foreach (['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3'] as $index => $levelName) {
            $level = ClassLevel::query()->updateOrCreate(
                ['name' => $levelName],
                ['sort_order' => $index + 1]
            );
            $classLevels[$levelName] = $level;

            foreach (['A', 'B', 'C'] as $arm) {
                $sections[$levelName][$arm] = Section::query()->updateOrCreate(
                    ['class_level_id' => $level->id, 'name' => $arm],
                    ['capacity' => 40]
                );
            }
        }

        $subjects = [];
        foreach ($this->subjects() as $code => $subjectName) {
            $subjects[$code] = Subject::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $subjectName]
            );
        }

        foreach ($subjects as $subject) {
            foreach ($classLevels as $level) {
                QuestionCategory::query()->updateOrCreate(
                    [
                        'parent_id' => null,
                        'slug' => Str::slug($subject->code.' '.$level->name.' general'),
                    ],
                    [
                        'subject_id' => $subject->id,
                        'class_level_id' => $level->id,
                        'name' => $subject->name.' '.$level->name.' General',
                    ]
                );
            }
        }

        foreach ($this->starterStaff() as $staff) {
            $user = User::query()->updateOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'username' => $staff['username'],
                    'password' => Hash::make($staffPassword),
                    'is_active' => true,
                ]
            );

            if ($role = Role::query()->where('name', $staff['role'])->first()) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $profile = StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'staff_number' => $staff['staff_number'],
                    'first_name' => $staff['first_name'],
                    'last_name' => $staff['last_name'],
                    'can_publish_exams' => $staff['role'] === 'exam-officer',
                ]
            );

            foreach ($staff['subject_codes'] as $subjectCode) {
                TeachingAssignment::query()->updateOrCreate([
                    'staff_profile_id' => $profile->id,
                    'subject_id' => $subjects[$subjectCode]->id,
                    'section_id' => $sections['JSS 1']['A']->id,
                ]);
            }
        }

        if ($this->option('sample-roster')) {
            Storage::disk('local')->put((string) $this->option('sample-roster-path'), $this->sampleRoster($session->name));
            $this->info('Sample roster written to local disk: '.$this->option('sample-roster-path'));
        }

        $this->info('School setup completed.');

        if (! $this->option('admin-password')) {
            $this->warn('Generated admin password: '.$password);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function subjects(): array
    {
        return [
            'MTH' => 'Mathematics',
            'ENG' => 'English Language',
            'BST' => 'Basic Science',
            'BTE' => 'Basic Technology',
            'CCA' => 'Cultural and Creative Arts',
            'CIV' => 'Civic Education',
            'CRS' => 'Christian Religious Studies',
            'IRS' => 'Islamic Religious Studies',
            'ECO' => 'Economics',
            'GOV' => 'Government',
            'BIO' => 'Biology',
            'CHE' => 'Chemistry',
            'PHY' => 'Physics',
            'GEO' => 'Geography',
            'COM' => 'Commerce',
            'ACC' => 'Financial Accounting',
            'LIT' => 'Literature in English',
            'DAT' => 'Data Processing',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function starterStaff(): array
    {
        return [
            [
                'name' => 'Exam Officer',
                'first_name' => 'Exam',
                'last_name' => 'Officer',
                'email' => 'exam.officer@school.test',
                'username' => 'exam.officer',
                'staff_number' => 'STF001',
                'role' => 'exam-officer',
                'subject_codes' => ['MTH'],
            ],
            [
                'name' => 'Question Author',
                'first_name' => 'Question',
                'last_name' => 'Author',
                'email' => 'question.author@school.test',
                'username' => 'question.author',
                'staff_number' => 'STF002',
                'role' => 'question-author',
                'subject_codes' => ['ENG'],
            ],
            [
                'name' => 'Invigilator',
                'first_name' => 'Exam',
                'last_name' => 'Invigilator',
                'email' => 'invigilator@school.test',
                'username' => 'invigilator',
                'staff_number' => 'STF003',
                'role' => 'invigilator',
                'subject_codes' => [],
            ],
            [
                'name' => 'Grader',
                'first_name' => 'Manual',
                'last_name' => 'Grader',
                'email' => 'grader@school.test',
                'username' => 'grader',
                'staff_number' => 'STF004',
                'role' => 'grader',
                'subject_codes' => ['ENG'],
            ],
        ];
    }

    private function sampleRoster(string $sessionName): string
    {
        return implode("\n", [
            'admission_number,first_name,last_name,middle_name,email,username,class_level,section,academic_session,status',
            'STD001,Amina,Lawal,,amina@example.test,amina,JSS 1,A,'.$sessionName.',active',
            'STD002,Musa,Bello,,musa@example.test,musa,JSS 1,A,'.$sessionName.',active',
            'STD003,Chidinma,Okafor,,chidinma@example.test,chidinma,JSS 1,B,'.$sessionName.',active',
            '',
        ]);
    }
}
