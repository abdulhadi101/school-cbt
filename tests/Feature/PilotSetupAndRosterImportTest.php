<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PilotSetupAndRosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_setup_creates_admin_and_defaults_idempotently(): void
    {
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
        $this->assertSame(18, Section::query()->count());
        $this->assertSame(18, Subject::query()->count());
        $this->assertSame(108, QuestionCategory::query()->count());
        $this->assertSame(4, StaffProfile::query()->count());
        $this->assertGreaterThanOrEqual(2, TeachingAssignment::query()->count());
        $this->assertTrue(User::query()->where('email', 'exam.officer@school.test')->first()->hasPermission('exams.publish'));
        $this->assertTrue(User::query()->where('email', 'invigilator@school.test')->first()->hasPermission('attempts.invigilate'));
    }

    public function test_school_setup_can_write_sample_roster(): void
    {
        Storage::fake('local');

        $this->artisan('school:setup', [
            '--admin-password' => 'secret-password',
            '--sample-roster' => true,
        ])->assertSuccessful();

        Storage::disk('local')->assertExists('rosters/sample-students.csv');
        $this->assertStringContainsString(
            'admission_number,first_name,last_name,middle_name,email,username,class_level,section,academic_session,status',
            Storage::disk('local')->get('rosters/sample-students.csv')
        );
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
