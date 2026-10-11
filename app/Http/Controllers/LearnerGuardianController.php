<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Http\Requests\StoreGuardianRequest;
use App\Http\Requests\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Models\Learner;
use App\Models\LearnerGuardian;
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

class LearnerGuardianController extends Controller
{
    public function index(Learner $learner): Response
    {
        Gate::authorize('view', $learner);
        try {
            $contacts = DB::transaction(function () use ($learner) {
                $contacts = $learner->guardianLinks()->with('guardian')->get()->map(function ($link) {
                    Gate::authorize('view', $link);
                    Gate::authorize('view', $link->guardian);

                    return [
                        'id' => $link->guardian_id, 'full_name' => $link->guardian->full_name,
                        'relationship' => $link->guardian->relationship, 'phone' => $link->guardian->phone,
                        'email' => $link->guardian->email, 'address' => $link->guardian->address,
                        'is_primary' => $link->is_primary, 'is_emergency' => $link->is_emergency,
                        'can_edit' => Gate::allows('update', $link->guardian) && Gate::allows('update', $link),
                    ];
                });
                app(AuditLearner::class)->record(request()->user(), $learner, 'guardians_viewed');

                return $contacts;
            });
        } catch (\Throwable $exception) {
            Log::error('Guardian view unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('guardians.unavailable'));
        }

        return Inertia::render('learners/guardians', [
            'learner' => ['id' => $learner->id, 'name' => trim($learner->first_name.' '.$learner->last_name), 'smile_id' => $learner->smile_id],
            'contacts' => $contacts, 'text' => __('guardians'), 'saved' => session('guardian_saved', false),
        ]);
    }

    public function store(StoreGuardianRequest $request, Learner $learner): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $learner) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', [Guardian::class, $learner]);
                $data = $request->validated();
                $guardian = new Guardian(collect($data)->only(['full_name', 'relationship', 'phone', 'email', 'address'])->all());
                $guardian->school_id = $actor->school_id;
                $guardian->save();
                $link = new LearnerGuardian(collect($data)->only(['is_primary', 'is_emergency'])->all());
                $link->school_id = $actor->school_id;
                $link->learner_id = $learner->id;
                $link->guardian_id = $guardian->id;
                $link->save();
                app(AuditLearner::class)->record($actor, $learner, 'guardian_created');
            });
        } catch (AuthorizationException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Guardian creation failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('guardians.save_failed')]);
        }

        return to_route('learners.guardians.index', $learner)->with('guardian_saved', true);
    }

    public function update(UpdateGuardianRequest $request, Learner $learner, Guardian $guardian): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $learner, $guardian) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                $guardian = Guardian::query()->whereKey($guardian->id)->lockForUpdate()->firstOrFail();
                $link = $learner->guardianLinks()->where('guardian_id', $guardian->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('view', $learner);
                Gate::forUser($actor)->authorize('update', $guardian);
                Gate::forUser($actor)->authorize('update', $link);
                $data = $request->validated();
                $guardian->fill(collect($data)->only(['full_name', 'relationship', 'phone', 'email', 'address'])->all())->save();
                $link->setPivotKeys('learner_id', 'guardian_id');
                $link->fill(collect($data)->only(['is_primary', 'is_emergency'])->all())->save();
                app(AuditLearner::class)->record($actor, $learner, 'guardian_updated');
            });
        } catch (AuthorizationException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Guardian update failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('guardians.save_failed')]);
        }

        return to_route('learners.guardians.index', $learner)->with('guardian_saved', true);
    }
}
