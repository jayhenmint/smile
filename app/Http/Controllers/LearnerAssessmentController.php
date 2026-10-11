<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Http\Requests\StoreAssessmentRecordRequest;
use App\Models\DisabilityCategory;
use App\Models\Learner;
use App\Models\LearnerDisability;
use App\Models\School;
use App\Models\TeacherAssessment;
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

class LearnerAssessmentController extends Controller
{
    public function index(Learner $learner): Response
    {
        Gate::authorize('view', $learner);
        Gate::authorize('viewAny', DisabilityCategory::class);
        try {
            $records = DB::transaction(function () use ($learner) {
                $teacher = TeacherAssessment::query()->where('learner_id', $learner->id)->orderByDesc('assessed_on')->orderByDesc('id')->get()->map(function ($record) {
                    Gate::authorize('view', $record);

                    return ['id' => $record->id, 'assessed_on' => $record->assessed_on->toDateString(), 'observations' => $record->observations];
                });
                $disabilities = LearnerDisability::query()->where('learner_id', $learner->id)->with('category')->orderByDesc('id')->get()->map(function ($record) {
                    Gate::authorize('view', $record);

                    return ['id' => $record->id, 'category' => $record->category->name, 'diagnosis_date' => $record->diagnosis_date, 'assessed_by' => $record->assessed_by, 'notes' => $record->notes];
                });
                app(AuditLearner::class)->record(request()->user(), $learner, 'assessments_viewed');

                return ['teacher' => $teacher, 'disabilities' => $disabilities];
            });
        } catch (\Throwable $exception) {
            Log::error('Assessment view unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('assessments.unavailable'));
        }

        return Inertia::render('learners/assessments', [
            'learner' => ['id' => $learner->id, 'name' => trim($learner->first_name.' '.$learner->last_name), 'smile_id' => $learner->smile_id],
            'records' => $records, 'categories' => DisabilityCategory::query()->orderBy('id')->get(['id', 'name']),
            'text' => __('assessments'), 'today' => now()->toDateString(), 'saved' => session('assessment_saved', false),
        ]);
    }

    public function store(StoreAssessmentRecordRequest $request, Learner $learner): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $learner) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                $data = $request->validated();
                $teacher = $data['kind'] === 'teacher';
                Gate::forUser($actor)->authorize('create', [$teacher ? TeacherAssessment::class : LearnerDisability::class, $learner]);
                $record = $teacher ? new TeacherAssessment(collect($data)->only(['assessed_on', 'observations'])->all())
                    : new LearnerDisability(collect($data)->only(['category_id', 'diagnosis_date', 'assessed_by', 'notes'])->all());
                $record->school_id = $actor->school_id;
                $record->learner_id = $learner->id;
                if ($teacher) {
                    $record->recorded_by = $actor->id;
                }
                $record->save();
                app(AuditLearner::class)->record($actor, $learner, $teacher ? 'teacher_assessment_created' : 'disability_record_created');
            });
        } catch (AuthorizationException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Assessment creation failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('assessments.save_failed')]);
        }

        return to_route('learners.assessments.index', $learner)->with('assessment_saved', true);
    }
}
