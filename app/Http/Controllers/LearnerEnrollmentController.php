<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\School;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LearnerEnrollmentController extends Controller
{
    public function create(Learner $learner): Response
    {
        Gate::authorize('reenroll', $learner);
        try {
            $data = DB::transaction(function () use ($learner) {
                $sections = Section::query()->where('adviser_id', request()->user()->id)->with('schoolYear')
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('enrollments')
                        ->where('enrollments.school_id', request()->user()->school_id)->where('enrollments.learner_id', $learner->id)
                        ->whereColumn('enrollments.school_year_id', 'sections.school_year_id'))
                    ->orderBy('name')->get()->map(function ($section) {
                        Gate::authorize('create', [Enrollment::class, $section]);

                        return ['id' => $section->id, 'name' => $section->name, 'year' => $section->schoolYear->label];
                    });
                app(AuditLearner::class)->record(request()->user(), $learner, 'enrollment_form_viewed');

                return ['learner' => ['id' => $learner->id, 'name' => trim($learner->first_name.' '.$learner->last_name), 'smile_id' => $learner->smile_id], 'sections' => $sections];
            });
        } catch (\Throwable $exception) {
            Log::error('Enrollment form unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('learners.enrollment_unavailable'));
        }

        return Inertia::render('learners/enroll', [...$data, 'text' => __('learners'), 'today' => now()->toDateString()]);
    }

    public function store(StoreEnrollmentRequest $request, Learner $learner): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $learner) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                $section = Section::query()->whereKey($request->validated('section_id'))->where('adviser_id', $actor->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('reenroll', $learner);
                Gate::forUser($actor)->authorize('create', [Enrollment::class, $section]);
                // Check all same-school enrollments, including years outside the adviser scope.
                if (DB::table('enrollments')->where('school_id', $actor->school_id)->where('learner_id', $learner->id)->where('school_year_id', $section->school_year_id)->exists()) {
                    throw ValidationException::withMessages(['section_id' => __('learners.already_enrolled')]);
                }
                $enrollment = new Enrollment(['enrolled_on' => $request->validated('enrolled_on')]);
                $enrollment->school_id = $actor->school_id;
                $enrollment->learner_id = $learner->id;
                $enrollment->school_year_id = $section->school_year_id;
                $enrollment->section_id = $section->id;
                $enrollment->level_status = 'non_graded';
                $enrollment->grade_level = null;
                $enrollment->save();
                app(AuditLearner::class)->record($actor, $learner, 'enrollment_created');
            });
        } catch (AuthorizationException|ModelNotFoundException|ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Enrollment creation failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('learners.save_failed')]);
        }

        return to_route('learners.show', $learner)->with('enrollment_saved', true);
    }
}
