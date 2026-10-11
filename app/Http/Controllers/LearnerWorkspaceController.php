<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Actions\CreateLearner;
use App\Http\Requests\StoreLearnerRequest;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LearnerWorkspaceController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Learner::class);
        try {
            $learners = DB::transaction(function () {
                $page = Learner::query()->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(15);
                $page->through(function (Learner $learner) {
                    Gate::authorize('view', $learner);
                    app(AuditLearner::class)->record(request()->user(), $learner, 'viewed');

                    return [
                        'id' => $learner->id, 'name' => trim($learner->first_name.' '.$learner->last_name),
                        'smile_id' => $learner->smile_id, 'lrn' => $learner->lrn,
                        'enrollments' => $learner->enrollments()->get()->map(fn ($enrollment) => [
                            'section' => Section::query()->find($enrollment->section_id)?->name,
                            'year' => SchoolYear::query()->find($enrollment->school_year_id)?->label,
                        ]),
                    ];
                });

                return $page;
            });
        } catch (\Throwable $exception) {
            Log::error('Learner roster unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('learners.unavailable'));
        }

        return Inertia::render('learners/index', [
            'learners' => $learners, 'text' => __('learners'),
            'created' => session('learner_created', false), 'duplicateWarning' => session('duplicate_warning', false),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('viewAny', Learner::class);

        return Inertia::render('learners/create', [
            'text' => __('learners'), 'today' => now(config('app.school_timezone'))->toDateString(),
            'sections' => Section::query()->where('adviser_id', request()->user()->id)->orderBy('name')->get()->map(fn ($section) => [
                'id' => $section->id, 'name' => $section->name,
                'year' => SchoolYear::query()->find($section->school_year_id)?->label,
            ]),
        ]);
    }

    public function store(StoreLearnerRequest $request, CreateLearner $create): RedirectResponse
    {
        try {
            $duplicate = $create->execute($request->user(), $request->validated());
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            // Do not report SQL bindings or sensitive attributes from the original exception.
            Log::error('Learner creation failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('learners.save_failed')]);
        }

        return to_route('learners.index')->with('learner_created', true)->with('duplicate_warning', $duplicate);
    }
}
