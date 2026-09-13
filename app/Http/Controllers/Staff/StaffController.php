<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles')
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', 'student'))
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name', 'label'),
                'last_login_at' => $user->last_login_at?->diffForHumans(),
            ]);

        return Inertia::render('Staff/Users/Staff/Index', [
            'users' => $users,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Staff/Users/Staff/Form', [
            'user' => null,
            'roles' => Role::query()->where('name', '!=', 'student')->orderBy('label')->get(['id', 'name', 'label']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'staff_number' => ['nullable', 'string', 'max:50', 'unique:staff_profiles,staff_number'],
            'can_publish_exams' => ['boolean'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $user->roles()->sync($data['role_ids']);

            StaffProfile::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'] ?? $data['name'],
                'last_name' => $data['last_name'] ?? '',
                'middle_name' => $data['middle_name'] ?? null,
                'staff_number' => $data['staff_number'] ?? null,
                'can_publish_exams' => (bool) ($data['can_publish_exams'] ?? false),
            ]);

            return $user;
        });

        return redirect()->route('staff.users.staff.index')->with('status', 'Staff account created.');
    }

    public function edit(User $user): Response
    {
        $user->load('roles', 'staffProfile');

        return Inertia::render('Staff/Users/Staff/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role_ids' => $user->roles->pluck('id')->values(),
                'first_name' => $user->staffProfile?->first_name,
                'last_name' => $user->staffProfile?->last_name,
                'middle_name' => $user->staffProfile?->middle_name,
                'staff_number' => $user->staffProfile?->staff_number,
                'can_publish_exams' => $user->staffProfile?->can_publish_exams ?? false,
            ],
            'roles' => Role::query()->where('name', '!=', 'student')->orderBy('label')->get(['id', 'name', 'label']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'staff_number' => ['nullable', 'string', 'max:50', 'unique:staff_profiles,staff_number,'.$user->staffProfile?->id],
            'can_publish_exams' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $user->roles()->sync($data['role_ids']);

            $profile = $user->staffProfile;

            if ($profile) {
                $profile->update([
                    'first_name' => $data['first_name'] ?? $data['name'],
                    'last_name' => $data['last_name'] ?? '',
                    'middle_name' => $data['middle_name'] ?? null,
                    'staff_number' => $data['staff_number'] ?? null,
                    'can_publish_exams' => (bool) ($data['can_publish_exams'] ?? false),
                ]);
            } else {
                StaffProfile::create([
                    'user_id' => $user->id,
                    'first_name' => $data['first_name'] ?? $data['name'],
                    'last_name' => $data['last_name'] ?? '',
                    'middle_name' => $data['middle_name'] ?? null,
                    'staff_number' => $data['staff_number'] ?? null,
                    'can_publish_exams' => (bool) ($data['can_publish_exams'] ?? false),
                ]);
            }
        });

        return redirect()->route('staff.users.staff.index')->with('status', 'Staff account updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Password has been reset.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->id === $request()->user()?->id) {
            throw ValidationException::withMessages(['user' => 'You cannot deactivate your own account.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'Account activated.' : 'Account deactivated.';

        return back()->with('status', $status);
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === $request()->user()?->id) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('staff.users.staff.index')->with('status', 'Staff account deleted.');
    }
}
