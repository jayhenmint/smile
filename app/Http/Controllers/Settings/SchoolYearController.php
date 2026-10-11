<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolYearController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', SchoolYear::class);

        return Inertia::render('settings/school-years', [
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->orderByDesc('id')->paginate(15)
                ->through(fn (SchoolYear $year) => [
                    'id' => $year->id, 'label' => $year->label,
                    'start_date' => $year->start_date->format('Y-m-d'),
                    'end_date' => $year->end_date->format('Y-m-d'), 'is_current' => $year->is_current,
                ]),
            'canManage' => Gate::allows('create', SchoolYear::class),
            'labels' => __('school_year_settings'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', SchoolYear::class);
        $this->save($request);

        return to_route('school-years.index');
    }

    public function update(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        Gate::authorize('update', $schoolYear);
        $this->save($request, $schoolYear);

        return to_route('school-years.index');
    }

    private function validated(Request $request, ?SchoolYear $year = null): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:100', Rule::unique('school_years', 'label')
                ->where('school_id', $request->user()->school_id)->ignore($year?->id)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_current' => ['required', 'boolean'],
            'school_id' => ['prohibited'],
            'id' => ['prohibited'],
        ]);
    }

    private function save(Request $request, ?SchoolYear $year = null): void
    {
        DB::transaction(function () use ($request, $year) {
            School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($year) {
                $year = SchoolYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $year);
            } else {
                Gate::forUser($actor)->authorize('create', SchoolYear::class);
                $year = new SchoolYear;
                $year->school_id = $actor->school_id;
            }
            // Validate uniqueness under the same school lock as the write.
            $data = $this->validated($request, $year->exists ? $year : null);
            if ($data['is_current']) {
                SchoolYear::query()->where('is_current', true)->update(['is_current' => false]);
            }
            $year->fill($data)->save();
        });
    }
}
