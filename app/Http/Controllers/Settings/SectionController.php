<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionTeacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Section::class);
        $canManage = Gate::allows('create', Section::class);

        return Inertia::render('settings/sections', [
            'sections' => Section::query()->with(['schoolYear', 'adviser'])->orderByDesc('school_year_id')->orderBy('name')->paginate(15)
                ->through(fn (Section $section) => [
                    'id' => $section->id, 'name' => $section->name, 'grade_level' => $section->grade_level,
                    'placement_type' => $section->placement_type, 'school_year_id' => $section->school_year_id,
                    'school_year_label' => $section->schoolYear->label, 'adviser_id' => $section->adviser_id,
                    'adviser_name' => $section->adviser?->name,
                ]),
            'schoolYears' => $canManage ? SchoolYear::query()->orderByDesc('start_date')->get(['id', 'label'])->toArray() : [],
            'advisers' => $canManage ? User::query()->where('is_active', true)->role('sped_teacher')
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'system_admin'))
                ->orderBy('name')->get(['id', 'name'])->toArray() : [],
            'canManage' => $canManage,
            'labels' => __('section_settings'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Section::class);
        $this->save($request);

        return to_route('sections.index');
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $this->save($request, $section);

        return to_route('sections.index');
    }

    private function save(Request $request, ?Section $section = null): void
    {
        DB::transaction(function () use ($request, $section) {
            School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($section) {
                $section = Section::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $section);
            } else {
                Gate::forUser($actor)->authorize('create', Section::class);
            }
            $data = $request->validate([
                'school_year_id' => $section ? ['prohibited'] : ['required', 'integer', Rule::exists('school_years', 'id')->where('school_id', $actor->school_id)],
                'name' => ['required', 'string', 'max:255', Rule::unique('sections', 'name')->where('school_id', $actor->school_id)
                    ->where('school_year_id', $section?->school_year_id ?? $request->input('school_year_id'))->ignore($section?->id)],
                'placement_type' => ['required', Rule::in(['sped_self_contained', 'resource_room', 'regular'])],
                'adviser_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('school_id', $actor->school_id)->where('is_active', true)],
                'school_id' => ['prohibited'], 'grade_level' => ['prohibited'], 'id' => ['prohibited'],
            ]);
            $adviserId = $data['adviser_id'] ?? null;
            if ($adviserId) {
                $adviser = User::query()->whereKey($adviserId)->lockForUpdate()->firstOrFail();
                if (! $adviser->hasRole('sped_teacher') || $adviser->hasRole('system_admin')) {
                    throw ValidationException::withMessages(['adviser_id' => __('section_settings.invalid_adviser')]);
                }
            }
            if (! $section) {
                $section = new Section;
                $section->school_id = $actor->school_id;
                $section->school_year_id = $data['school_year_id'];
            }
            $section->fill(['name' => $data['name'], 'placement_type' => $data['placement_type'], 'adviser_id' => $adviserId])->save();
            SectionTeacher::query()->where('section_id', $section->id)->where('role', 'adviser')->delete();
            if ($adviserId) {
                // The authorized section and validated teacher supply both keys.
                DB::table('section_teachers')->updateOrInsert(['section_id' => $section->id, 'user_id' => $adviserId],
                    ['school_id' => $actor->school_id, 'role' => 'adviser']);
            }
        });
    }
}
