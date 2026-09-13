<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $students = Student::query()
            ->with('user', 'enrollments.section.classLevel')
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query
                ->where(fn ($q) => $q
                    ->where('admission_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Student $student): array => [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'status' => $student->status->value,
                'has_account' => $student->user !== null,
                'email' => $student->user?->email,
                'is_active' => $student->user?->is_active ?? false,
                'current_enrollment' => $student->enrollments->where('is_current', true)->first()?->section
                    ? ['section' => $student->enrollments->where('is_current', true)->first()->section->name, 'class_level' => $student->enrollments->where('is_current', true)->first()->section->classLevel->name]
                    : null,
            ]);

        return Inertia::render('Staff/Users/Students/Index', [
            'students' => $students,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $s): array => ['value' => $s->value, 'label' => ucfirst($s->value)]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Staff/Users/Students/Form', [
            'student' => null,
            'class_levels' => ClassLevel::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'sections' => Section::query()->with('classLevel:id,name')->orderBy('name')->get(['id', 'name', 'class_level_id']),
            'current_session' => AcademicSession::query()->where('is_current', true)->first(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admission_number' => ['required', 'string', 'max:50', 'unique:students,admission_number'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required_with:email', 'confirmed', Rules\Password::defaults()],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        DB::transaction(function () use ($data): void {
            $user = null;

            if (! empty($data['email'])) {
                $role = Role::query()->where('name', 'student')->first();

                $user = User::create([
                    'name' => $data['first_name'].' '.$data['last_name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'is_active' => true,
                ]);

                if ($role) {
                    $user->roles()->attach($role);
                }
            }

            $student = Student::create([
                'user_id' => $user?->id,
                'admission_number' => $data['admission_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'status' => StudentStatus::Active,
            ]);

            if (! empty($data['section_id'])) {
                $currentSession = AcademicSession::query()->where('is_current', true)->first();

                if ($currentSession) {
                    Enrollment::create([
                        'student_id' => $student->id,
                        'academic_session_id' => $currentSession->id,
                        'section_id' => $data['section_id'],
                        'is_current' => true,
                    ]);
                }
            }
        });

        return redirect()->route('staff.users.students.index')->with('status', 'Student created.');
    }

    public function edit(Student $student): Response
    {
        $student->load('user', 'enrollments.section.classLevel', 'enrollments.academicSession');

        return Inertia::render('Staff/Users/Students/Form', [
            'student' => [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'status' => $student->status->value,
                'has_account' => $student->user !== null,
                'email' => $student->user?->email,
                'is_active' => $student->user?->is_active ?? false,
                'current_section_id' => $student->enrollments->where('is_current', true)->first()?->section_id,
            ],
            'class_levels' => ClassLevel::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'sections' => Section::query()->with('classLevel:id,name')->orderBy('name')->get(['id', 'name', 'class_level_id']),
            'current_session' => AcademicSession::query()->where('is_current', true)->first(['id', 'name']),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'admission_number' => ['required', 'string', 'max:50', 'unique:students,admission_number,'.$student->id],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.','.$student->user_id],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        DB::transaction(function () use ($student, $data): void {
            $student->update([
                'admission_number' => $data['admission_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'status' => $data['status'],
            ]);

            $user = $student->user;

            if ($user) {
                $user->update([
                    'name' => $data['first_name'].' '.$data['last_name'],
                    'email' => $data['email'] ?? $user->email,
                ]);
            }

            if (! empty($data['section_id'])) {
                $currentSession = AcademicSession::query()->where('is_current', true)->first();

                if ($currentSession) {
                    $enrollment = Enrollment::where('student_id', $student->id)
                        ->where('academic_session_id', $currentSession->id)
                        ->first();

                    if ($enrollment) {
                        $enrollment->update(['section_id' => $data['section_id']]);
                    } else {
                        Enrollment::create([
                            'student_id' => $student->id,
                            'academic_session_id' => $currentSession->id,
                            'section_id' => $data['section_id'],
                            'is_current' => true,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('staff.users.students.index')->with('status', 'Student updated.');
    }

    public function createAccount(Student $student): RedirectResponse
    {
        if ($student->user) {
            return back()->withErrors(['student' => 'This student already has a login account.']);
        }

        $email = $student->admission_number.'@student.school.test';
        $password = 'student12345';

        $role = Role::query()->where('name', 'student')->first();

        $user = User::create([
            'name' => $student->first_name.' '.$student->last_name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        if ($role) {
            $user->roles()->attach($role);
        }

        $student->update(['user_id' => $user->id]);

        return back()->with('status', "Account created. Email: {$email}, Password: {$password}");
    }

    public function resetPassword(Student $student): RedirectResponse
    {
        if (! $student->user) {
            return back()->withErrors(['student' => 'This student does not have a login account.']);
        }

        $data = request()->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $student->user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Password has been reset.');
    }

    public function toggleActive(Student $student): RedirectResponse
    {
        if (! $student->user) {
            return back()->withErrors(['student' => 'This student does not have a login account.']);
        }

        $student->user->update(['is_active' => ! $student->user->is_active]);

        $status = $student->user->is_active ? 'Account activated.' : 'Account deactivated.';

        return back()->with('status', $status);
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('staff.users.students.index')->with('status', 'Student deleted.');
    }
}
