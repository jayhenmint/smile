<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function edit(Request $request): Response
    {
        $school = $this->editableSchool($request);

        return Inertia::render('settings/school', [
            'school' => $school->only(['name', 'deped_school_id', 'division', 'region', 'address']),
            'labels' => __('school_settings'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $this->editableSchool($request);
        $school->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'deped_school_id' => ['nullable', 'string', 'regex:/^[0-9]{6}$/', Rule::unique('schools', 'deped_school_id')->ignore($school->id)],
            'division' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]));

        return to_route('school.edit');
    }

    private function editableSchool(Request $request): School
    {
        // The authenticated membership is authoritative; no request school ID is accepted.
        $school = School::findOrFail($request->user()->school_id);
        Gate::authorize('update', $school);

        return $school;
    }
}
