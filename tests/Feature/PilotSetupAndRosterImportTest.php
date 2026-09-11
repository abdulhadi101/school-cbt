<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotSetupAndRosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_setup_creates_admin_and_defaults_idempotently(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        $this->artisan('school:setup', [
            '--name' => 'Demo College',
            '--admin-email' => 'admin@example.test',
            '--admin-password' => 'secret-password',
        ])->assertSuccessful();

        $this->artisan('school:setup', [
            '--name' => 'Demo College',
            '--admin-email' => 'admin@example.test',
            '--admin-password' => 'secret-password',
        ])->assertSuccessful();

        $this->assertSame(1, SchoolSetting::query()->count());
        $this->assertSame(1, User::query()->where('email', 'admin@example.test')->count());
        $this->assertTrue(User::query()->where('email', 'admin@example.test')->first()->hasPermission('system.manage'));
        $this->assertGreaterThanOrEqual(6, Section::query()->count());
        $this->assertGreaterThanOrEqual(4, Subject::query()->count());
    }

    public function test_roster_import_creates_students_users_and_enrollments(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        AcademicSession::query()->create(['name' => '2026/2027', 'is_current' => true]);
        $path = $this->writeRoster("admission_number,first_name,last_name,middle_name,email,username,class_level,section,academic_session\nSTD001,Amina,Lawal,,amina@example.test,amina,JSS 1,A,2026/2027\nSTD002,Musa,Bello,,musa@example.test,musa,JSS 1,A,2026/2027\n");

        $this->artisan('roster:import', [
            'path' => $path,
            '--create-users' => true,
            '--default-password' => 'student123',
        ])->assertSuccessful();

        $this->assertSame(2, Student::query()->count());
        $this->assertSame(2, Enrollment::query()->count());
        $this->assertSame(2, User::query()->count());
        $this->assertTrue(User::query()->where('email', 'amina@example.test')->first()->hasPermission('attempts.take'));
    }

    public function test_roster_import_updates_existing_student(): void
    {
        AcademicSession::query()->create(['name' => '2026/2027', 'is_current' => true]);
        Student::query()->create([
            'admission_number' => 'STD001',
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);
        $path = $this->writeRoster("admission_number,first_name,last_name,class_level,section,academic_session\nSTD001,Amina,Lawal,JSS 2,B,2026/2027\n");

        $this->artisan('roster:import', ['path' => $path])->assertSuccessful();

        $student = Student::query()->where('admission_number', 'STD001')->first();
        $this->assertSame('Amina', $student->first_name);
        $this->assertSame(1, Student::query()->count());
        $this->assertSame(1, Enrollment::query()->count());
    }

    public function test_roster_import_reports_invalid_rows(): void
    {
        $path = $this->writeRoster("admission_number,first_name,last_name,email\nSTD001,Amina,Lawal,not-an-email\n,No,Admission,no@example.test\n");

        $this->artisan('roster:import', ['path' => $path])->assertFailed();

        $this->assertSame(0, Student::query()->count());
    }

    private function writeRoster(string $contents): string
    {
        $path = storage_path('framework/testing/roster-'.uniqid().'.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
