<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Http\Requests\StoreIssuedLrnRequest;
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

class LearnerLrnController extends Controller
{
    public function store(StoreIssuedLrnRequest $request, Learner $learner): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $learner) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('recordLrn', $learner);
                $lrn = $request->validated('lrn');
                // Only availability is returned; matching records remain private.
                if (DB::table('learners')->where('lrn', $lrn)->exists()) {
                    throw ValidationException::withMessages(['lrn' => __('learners.lrn_unavailable')]);
                }
                $learner->lrn = $lrn;
                $learner->lrn_status = 'issued';
                $learner->save();
                app(AuditLearner::class)->record($actor, $learner, 'lrn_recorded');
            });
        } catch (AuthorizationException|ModelNotFoundException|ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Learner LRN recording failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('learners.save_failed')]);
        }

        return to_route('learners.show', $learner)->with('lrn_recorded', true);
    }
}
