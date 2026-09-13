<?php

namespace App\Http\Middleware;

use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->roles->flatMap(fn ($role) => $role->permissions->pluck('name'))->unique()->values()->all() : [],
            ],
            'school' => Cache::remember('school:settings', 300, function (): array {
                $school = SchoolSetting::query()->first();

                return [
                    'name' => $school?->name ?? 'School CBT',
                    'code' => $school?->code,
                    'address' => $school?->address,
                    'phone' => $school?->phone,
                    'email' => $school?->email,
                    'logo_url' => $school?->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                    'motto' => $school?->settings['motto'] ?? null,
                    'website' => $school?->settings['website'] ?? null,
                    'academic_year' => $school?->settings['academic_year'] ?? null,
                ];
            }),
        ];
    }
}
