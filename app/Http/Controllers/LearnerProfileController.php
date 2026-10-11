<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Http\Requests\UpdateLearnerRequest;
use App\Models\Learner;
use App\Models\School;
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

class LearnerProfileController extends Controller
{
    public function show(Learner $learner): Response
    {
        return $this->profile($learner, false);
    }

    public function edit(Learner $learner): Response
    {
        return $this->profile($learner, true);
    }

    private function profile(Learner $learner, bool $editing): Response
    {
        Gate::authorize($editing ? 'update' : 'view', $learner);
        try {
            $data = DB::transaction(function () use ($learner) {
                $data = [
                    'id' => $learner->id, 'smile_id' => $learner->smile_id, 'lrn' => $learner->lrn, 'lrn_status' => $learner->lrn_status,
                    'first_name' => $learner->first_name, 'last_name' => $learner->last_name, 'middle_name' => $learner->middle_name,
                    'birthdate' => $learner->birthdate->toDateString(), 'sex' => $learner->sex, 'address' => $learner->address,
                    'mother_tongue' => $learner->mother_tongue, 'health_notes' => $learner->health_notes,
                    'consent_confirmed_at' => $learner->consent_confirmed_at->toIso8601String(),
                    'enrollments' => $learner->enrollments()->with(['section', 'schoolYear'])->get()->map(fn ($enrollment) => [
                        'year' => $enrollment->schoolYear?->label, 'section' => $enrollment->section?->name,
                        'enrolled_on' => $enrollment->enrolled_on->toDateString(),
                    ]),
                ];
                app(AuditLearner::class)->record(request()->user(), $learner, 'viewed');

                return $data;
            });
        } catch (\Throwable $exception) {
            Log::error('Learner profile unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('learners.unavailable'));
        }

        return Inertia::render('learners/profile', ['learner' => $data, 'editing' => $editing, 'text' => __('learners'), 'updated' => session('learner_updated', false), 'lrnRecorded' => session('lrn_recorded', false), 'canRecordLrn' => Gate::allows('recordLrn', $learner), 'canReenroll' => Gate::allows('reenroll', $learner), 'enrollmentSaved' => session('enrollment_saved', false)]);
    }

    public function update(UpdateLearnerRequest $request, Learner $learner): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $learner) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $learner);
                $learner->fill(collect($request->validated())->only([
                    'first_name', 'last_name', 'middle_name', 'birthdate', 'sex', 'address', 'mother_tongue', 'health_notes',
                ])->all());
                $learner->save();
                app(AuditLearner::class)->record($actor, $learner, 'updated');
            });
        } catch (AuthorizationException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Learner update failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('learners.save_failed')]);
        }

        return to_route('learners.show', $learner)->with('learner_updated', true);
    }
}
