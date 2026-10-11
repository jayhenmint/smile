<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Http\Requests\SaveLearnerDocumentRequest;
use App\Models\DocumentType;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LearnerDocumentController extends Controller
{
    public function index(Learner $learner): Response
    {
        Gate::authorize('view', $learner);
        try {
            $items = DB::transaction(function () use ($learner) {
                $records = LearnerDocument::query()->where('learner_id', $learner->id)->get()->keyBy('document_type_id');
                $items = DocumentType::query()->orderBy('id')->get()->map(function ($type) use ($records, $learner) {
                    $record = $records->get($type->id);
                    if ($record) {
                        Gate::authorize('view', $record);
                    }

                    return ['id' => $type->id, 'name' => __('documents.types.'.$type->id), 'required_for' => $type->required_for,
                        'from_party' => $type->from_party, 'status' => $record?->status ?? 'missing',
                        'submitted_on' => $record?->submitted_on?->format('Y-m-d'), 'notes' => $record?->notes,
                        'record_id' => $record?->id, 'has_file' => $record !== null && $record->file_url !== null,
                        'download_url' => $record?->file_url ? URL::temporarySignedRoute('learners.documents.files.download', now()->addMinutes(config('document_uploads.link_minutes')),
                            ['learner' => $learner->id, 'document' => $record->id, 'user' => request()->user()->id]) : null];
                });
                app(AuditLearner::class)->record(request()->user(), $learner, 'documents_viewed');

                return $items;
            });
        } catch (\Throwable $exception) {
            Log::error('Document checklist unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('documents.unavailable'));
        }

        return Inertia::render('learners/documents', ['learner' => ['id' => $learner->id, 'name' => trim($learner->first_name.' '.$learner->last_name), 'smile_id' => $learner->smile_id],
            'items' => $items, 'text' => __('documents'), 'saved' => session('document_saved', false), 'uploaded' => session('document_uploaded', false), 'maxUploadKb' => config('document_uploads.max_kb')]);
    }

    public function save(SaveLearnerDocumentRequest $request, Learner $learner, DocumentType $documentType): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $learner, $documentType) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', [LearnerDocument::class, $learner]);
                $record = LearnerDocument::query()->where('learner_id', $learner->id)->where('document_type_id', $documentType->id)->lockForUpdate()->first();
                if ($record) {
                    Gate::forUser($actor)->authorize('update', $record);
                } else {
                    $record = new LearnerDocument;
                    $record->school_id = $learner->school_id;
                    $record->learner_id = $learner->id;
                    $record->document_type_id = $documentType->id;
                }
                $record->status = $request->validated('status');
                $record->submitted_on = $record->status === 'missing' ? null : $request->validated('submitted_on');
                $record->notes = $request->validated('notes');
                $record->verified_by = $record->status === 'verified' ? $actor->id : null;
                $record->save();
                app(AuditLearner::class)->record($actor, $learner, 'document_checklist_updated');
            });
        } catch (AuthorizationException|ModelNotFoundException|ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Document checklist save failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['form' => __('documents.save_failed')]);
        }

        return to_route('learners.documents.index', $learner)->with('document_saved', true);
    }
}
