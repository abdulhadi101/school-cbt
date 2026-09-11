<?php

namespace App\Services\Imports;

use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class RosterImporter
{
    /**
     * @param  array{create_users?: bool, default_password?: string|null}  $options
     */
    public function import(string $path, array $options = []): RosterImportResult
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Roster file cannot be read.');
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('Roster file cannot be opened.');
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            throw new RuntimeException('Roster file must include a header row.');
        }

        $headers = array_map(fn ($header) => Str::snake(trim((string) $header)), $headers);
        $result = new RosterImportResult;
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $data = $this->combineRow($headers, $row);

            if ($this->isBlankRow($data)) {
                $result->skipped++;

                continue;
            }

            $error = $this->validateRow($data, $line);

            if ($error) {
                $result->errors[] = $error;
                $result->skipped++;

                continue;
            }

            DB::transaction(function () use ($data, $options, $result) {
                $existing = Student::query()->where('admission_number', $data['admission_number'])->first();
                $student = Student::query()->updateOrCreate(
                    ['admission_number' => $data['admission_number']],
                    [
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'middle_name' => $data['middle_name'] ?: null,
                        'status' => $data['status'] ?: 'active',
                    ]
                );

                if (($options['create_users'] ?? false) === true) {
                    $this->attachLoginUser($student, $data, $options['default_password'] ?? null);
                }

                $this->enrollStudent($student, $data);

                $existing ? $result->updated++ : $result->created++;
            });
        }

        fclose($handle);

        return $result;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function combineRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return array_merge([
            'admission_number' => '',
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'email' => '',
            'username' => '',
            'class_level' => '',
            'section' => '',
            'academic_session' => '',
            'status' => 'active',
        ], $data);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function isBlankRow(array $data): bool
    {
        return collect($data)->every(fn ($value) => trim((string) $value) === '');
    }

    /**
     * @param  array<string, string>  $data
     */
    private function validateRow(array $data, int $line): ?string
    {
        foreach (['admission_number', 'first_name', 'last_name'] as $field) {
            if ($data[$field] === '') {
                return "Line {$line}: {$field} is required.";
            }
        }

        if ($data['email'] !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return "Line {$line}: email is invalid.";
        }

        return null;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function attachLoginUser(Student $student, array $data, ?string $defaultPassword): void
    {
        $email = $data['email'] ?: strtolower($data['admission_number']).'@student.local';
        $username = $data['username'] ?: $data['admission_number'];

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'username' => $username,
                'password' => Hash::make($defaultPassword ?: $data['admission_number']),
                'is_active' => true,
            ]
        );

        $student->forceFill(['user_id' => $user->id])->save();

        if ($role = Role::query()->where('name', 'student')->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    private function enrollStudent(Student $student, array $data): void
    {
        if ($data['class_level'] === '' || $data['section'] === '') {
            return;
        }

        $session = $data['academic_session'] !== ''
            ? AcademicSession::query()->firstOrCreate(['name' => $data['academic_session']], ['is_current' => false])
            : AcademicSession::query()->firstOrCreate(
                ['is_current' => true],
                ['name' => now()->year.'/'.now()->addYear()->year]
            );

        $level = ClassLevel::query()->firstOrCreate(['name' => $data['class_level']]);
        $section = Section::query()->firstOrCreate([
            'class_level_id' => $level->id,
            'name' => $data['section'],
        ]);

        Enrollment::query()->updateOrCreate(
            ['student_id' => $student->id, 'academic_session_id' => $session->id],
            ['section_id' => $section->id, 'is_current' => true]
        );
    }
}
