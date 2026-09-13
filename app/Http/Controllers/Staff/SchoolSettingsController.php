<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SchoolSettingsController extends Controller
{
    public function edit(): Response
    {
        $school = SchoolSetting::query()->firstOrCreate([], [
            'name' => 'School CBT',
        ]);

        return Inertia::render('Staff/Settings/School', [
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'address' => $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                'settings' => $school->settings,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = SchoolSetting::query()->firstOrCreate([], [
            'name' => 'School CBT',
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'max:2048'],
            'remove_logo' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'settings.motto' => ['nullable', 'string', 'max:255'],
            'settings.website' => ['nullable', 'url', 'max:255'],
            'settings.academic_year' => ['nullable', 'string', 'max:50'],
        ]);

        if ($request->boolean('remove_logo') && $school->logo_path) {
            Storage::disk('public')->delete($school->logo_path);
            $school->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($school->logo_path) {
                Storage::disk('public')->delete($school->logo_path);
            }
            $school->logo_path = $request->file('logo')->store('school', 'public');
        }

        $school->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'settings' => $data['settings'] ?? $school->settings,
        ]);

        return back()->with('status', 'School settings updated.');
    }
}
