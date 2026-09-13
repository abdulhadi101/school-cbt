<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Role;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportController extends Controller
{
    public function staffTemplate(): StreamedResponse
    {
        $headers = ['name', 'email', 'password', 'roles', 'first_name', 'last_name', 'middle_name', 'staff_number', 'can_publish_exams'];
        $example = ['John Doe', 'john@school.test', 'password123', 'exam-officer,grader', 'John', 'Doe', 'M', 'STF001', 'yes'];

        return response()->streamDownload(function () use ($headers, $example): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $example);
            fclose($handle);
        }, 'staff_import_template.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="staff_import_template.csv"',
        ]);
    }

    public function staffExport(): StreamedResponse
    {
        $users = User::query()
            ->with('roles', 'staffProfile')
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', 'student'))
            ->orderBy('name')
            ->get();

        $headers = ['name', 'email', 'password', 'roles', 'first_name', 'last_name', 'middle_name', 'staff_number', 'can_publish_exams'];

        return response()->streamDownload(function () use ($users, $headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    '',
                    $user->roles->pluck('name')->implode(','),
                    $user->staffProfile?->first_name ?? '',
                    $user->staffProfile?->last_name ?? '',
                    $user->staffProfile?->middle_name ?? '',
                    $user->staffProfile?->staff_number ?? '',
                    $user->staffProfile?->can_publish_exams ? 'yes' : 'no',
                ]);
            }

            fclose($handle);
        }, 'staff_export_'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="staff_export_'.date('Y-m-d').'.csv"',
        ]);
    }

    public function staffImportForm(): Response
    {
        return Inertia::render('Staff/Users/Staff/Import', [
            'preview' => null,
            'roles' => Role::query()->where('name', '!=', 'student')->orderBy('label')->get(['id', 'name', 'label']),
        ]);
    }

    public function staffPreview(Request $request): Response
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows = $this->parseCsv($request->file('csv_file'));
        $headers = array_shift($rows);
        $mapped = $this->mapStaffRows($headers, $rows);
        $limited = array_slice($mapped, 0, 200);

        return Inertia::render('Staff/Users/Staff/Import', [
            'preview' => [
                'total' => count($mapped),
                'rows' => $limited,
                'headers' => $headers,
            ],
            'roles' => Role::query()->where('name', '!=', 'student')->orderBy('label')->get(['id', 'name', 'label']),
        ]);
    }

    public function staffImport(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows = $this->parseCsv($request->file('csv_file'));
        $headers = array_shift($rows);
        $mapped = $this->mapStaffRows($headers, $rows);
        $roles = Role::query()->where('name', '!=', 'student')->get()->keyBy('name');

        $result = DB::transaction(function () use ($mapped, $roles): array {
            $created = 0;
            $errors = [];

            foreach ($mapped as $index => $row) {
                try {
                    if (empty($row['email']) || empty($row['name'])) {
                        $errors[] = ['row' => $index + 2, 'message' => 'Name and email are required.'];

                        continue;
                    }

                    if (User::where('email', $row['email'])->exists()) {
                        $errors[] = ['row' => $index + 2, 'message' => "Email '{$row['email']}' already exists."];

                        continue;
                    }

                    $password = ! empty($row['password']) ? $row['password'] : 'password12345';

                    $user = User::create([
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'password' => Hash::make($password),
                        'is_active' => true,
                    ]);

                    $roleNames = array_map('trim', explode(',', $row['roles'] ?? ''));
                    $roleIds = collect($roleNames)->filter(fn ($name) => $roles->has($name))->pluck('id')->all();
                    if ($roleIds !== []) {
                        $user->roles()->sync($roleIds);
                    }

                    StaffProfile::create([
                        'user_id' => $user->id,
                        'first_name' => $row['first_name'] ?? $row['name'],
                        'last_name' => $row['last_name'] ?? '',
                        'middle_name' => $row['middle_name'] ?? null,
                        'staff_number' => $row['staff_number'] ?? null,
                        'can_publish_exams' => in_array(strtolower($row['can_publish_exams'] ?? ''), ['yes', 'true', '1'], true),
                    ]);

                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $index + 2, 'message' => $e->getMessage()];
                }
            }

            return ['created' => $created, 'errors' => $errors];
        });

        if ($result['errors'] !== []) {
            return back()->with('import_result', $result);
        }

        return redirect()->route('staff.users.staff.index')->with('status', "{$result['created']} staff imported successfully.");
    }

    public function studentTemplate(): StreamedResponse
    {
        $headers = ['admission_number', 'first_name', 'last_name', 'middle_name', 'email', 'password', 'section'];
        $example = ['STU001', 'Jane', 'Smith', 'A', 'jane@student.school.test', 'password123', 'JSS1-A'];

        return response()->streamDownload(function () use ($headers, $example): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $example);
            fclose($handle);
        }, 'student_import_template.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_import_template.csv"',
        ]);
    }

    public function studentExport(): StreamedResponse
    {
        $students = Student::query()
            ->with('user', 'enrollments.section.classLevel', 'enrollments.academicSession')
            ->orderBy('admission_number')
            ->get();

        $headers = ['admission_number', 'first_name', 'last_name', 'middle_name', 'email', 'password', 'section'];

        return response()->streamDownload(function () use ($students, $headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($students as $student) {
                $enrollment = $student->enrollments->where('is_current', true)->first();
                $sectionName = '';

                if ($enrollment?->section) {
                    $sectionName = $enrollment->section->classLevel?->name.' '.$enrollment->section->name;
                }

                fputcsv($handle, [
                    $student->admission_number,
                    $student->first_name,
                    $student->last_name,
                    $student->middle_name ?? '',
                    $student->user?->email ?? '',
                    '',
                    $sectionName,
                ]);
            }

            fclose($handle);
        }, 'student_export_'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_export_'.date('Y-m-d').'.csv"',
        ]);
    }

    public function studentImportForm(): Response
    {
        return Inertia::render('Staff/Users/Students/Import', [
            'preview' => null,
            'sections' => Section::query()->with('classLevel:id,name')->orderBy('name')->get(['id', 'name', 'class_level_id']),
        ]);
    }

    public function studentPreview(Request $request): Response
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows = $this->parseCsv($request->file('csv_file'));
        $headers = array_shift($rows);
        $mapped = $this->mapStudentRows($headers, $rows);
        $limited = array_slice($mapped, 0, 200);

        return Inertia::render('Staff/Users/Students/Import', [
            'preview' => [
                'total' => count($mapped),
                'rows' => $limited,
                'headers' => $headers,
            ],
            'sections' => Section::query()->with('classLevel:id,name')->orderBy('name')->get(['id', 'name', 'class_level_id']),
        ]);
    }

    public function studentImport(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows = $this->parseCsv($request->file('csv_file'));
        $headers = array_shift($rows);
        $mapped = $this->mapStudentRows($headers, $rows);
        $sections = Section::query()->with('classLevel')->get();
        $currentSession = AcademicSession::query()->where('is_current', true)->first();
        $studentRole = Role::query()->where('name', 'student')->first();

        $result = DB::transaction(function () use ($mapped, $sections, $currentSession, $studentRole): array {
            $created = 0;
            $errors = [];

            foreach ($mapped as $index => $row) {
                try {
                    if (empty($row['admission_number']) || empty($row['first_name']) || empty($row['last_name'])) {
                        $errors[] = ['row' => $index + 2, 'message' => 'Admission number, first name, and last name are required.'];

                        continue;
                    }

                    if (Student::where('admission_number', $row['admission_number'])->exists()) {
                        $errors[] = ['row' => $index + 2, 'message' => "Admission number '{$row['admission_number']}' already exists."];

                        continue;
                    }

                    $user = null;

                    if (! empty($row['email'])) {
                        if (User::where('email', $row['email'])->exists()) {
                            $errors[] = ['row' => $index + 2, 'message' => "Email '{$row['email']}' already exists."];

                            continue;
                        }

                        $password = ! empty($row['password']) ? $row['password'] : 'student12345';

                        $user = User::create([
                            'name' => $row['first_name'].' '.$row['last_name'],
                            'email' => $row['email'],
                            'password' => Hash::make($password),
                            'is_active' => true,
                        ]);

                        if ($studentRole) {
                            $user->roles()->attach($studentRole);
                        }
                    }

                    $student = Student::create([
                        'user_id' => $user?->id,
                        'admission_number' => $row['admission_number'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'middle_name' => $row['middle_name'] ?? null,
                        'status' => StudentStatus::Active,
                    ]);

                    if (! empty($row['section']) && $currentSession) {
                        $section = $this->findSection($sections, $row['section']);

                        if ($section) {
                            Enrollment::create([
                                'student_id' => $student->id,
                                'academic_session_id' => $currentSession->id,
                                'section_id' => $section->id,
                                'is_current' => true,
                            ]);
                        }
                    }

                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $index + 2, 'message' => $e->getMessage()];
                }
            }

            return ['created' => $created, 'errors' => $errors];
        });

        if ($result['errors'] !== []) {
            return back()->with('import_result', $result);
        }

        return redirect()->route('staff.users.students.index')->with('status', "{$result['created']} students imported successfully.");
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array<string, string>>
     */
    private function mapStaffRows(array $headers, array $rows): array
    {
        $normalizedHeaders = array_map(fn (string $h): string => strtolower(trim(str_replace(' ', '_', $h))), $headers);

        return array_map(function (array $row) use ($normalizedHeaders): array {
            $data = array_combine($normalizedHeaders, array_pad($row, count($normalizedHeaders), ''));

            return [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
                'password' => $data['password'] ?? '',
                'roles' => $data['roles'] ?? '',
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'middle_name' => $data['middle_name'] ?? '',
                'staff_number' => $data['staff_number'] ?? '',
                'can_publish_exams' => $data['can_publish_exams'] ?? '',
            ];
        }, $rows);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array<string, string>>
     */
    private function mapStudentRows(array $headers, array $rows): array
    {
        $normalizedHeaders = array_map(fn (string $h): string => strtolower(trim(str_replace(' ', '_', $h))), $headers);

        return array_map(function (array $row) use ($normalizedHeaders): array {
            $data = array_combine($normalizedHeaders, array_pad($row, count($normalizedHeaders), ''));

            return [
                'admission_number' => $data['admission_number'] ?? $data['adm_no'] ?? '',
                'first_name' => $data['first_name'] ?? $data['firstname'] ?? '',
                'last_name' => $data['last_name'] ?? $data['lastname'] ?? '',
                'middle_name' => $data['middle_name'] ?? $data['middlename'] ?? '',
                'email' => $data['email'] ?? '',
                'password' => $data['password'] ?? '',
                'section' => $data['section'] ?? $data['class'] ?? '',
            ];
        }, $rows);
    }

    private function findSection(Collection $sections, string $sectionName): ?Section
    {
        $name = trim($sectionName);

        foreach ($sections as $section) {
            $fullName = trim(($section->classLevel?->name ?? '').' '.$section->name);

            if (strtolower($fullName) === strtolower($name) || strtolower($section->name) === strtolower($name)) {
                return $section;
            }
        }

        return null;
    }
}
