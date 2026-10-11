<?php

namespace App\Http\Controllers\Settings;

use App\Actions\AuditLearner;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ActivityLog::class);
        try {
            $entries = DB::transaction(function () {
                $entries = ActivityLog::query()->with(['subject', 'causer'])->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
                $learners = [];
                foreach ($entries as $entry) {
                    Gate::authorize('view', $entry);
                    $learners[$entry->subject_id] = $entry->subject;
                }
                foreach ($learners as $learner) {
                    app(AuditLearner::class)->record(request()->user(), $learner, 'audit_history_viewed');
                }

                return $entries->through(fn ($entry) => [
                    'id' => $entry->id, 'occurred_at' => $entry->created_at?->toIso8601String(),
                    'action' => Lang::has('audit_log.events.'.$entry->event) ? __('audit_log.events.'.$entry->event) : __('audit_log.generic_event'),
                    'smile_id' => $entry->subject->smile_id,
                    'actor' => $entry->causer?->name ?? __('audit_log.account_unavailable'),
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Learner audit history unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('audit_log.unavailable'));
        }

        return Inertia::render('settings/audit-log', ['entries' => $entries, 'text' => __('audit_log')]);
    }
}
