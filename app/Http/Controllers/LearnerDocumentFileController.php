<?php

namespace App\Http\Controllers;

use App\Actions\AuditLearner;
use App\Http\Requests\UploadLearnerDocumentRequest;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LearnerDocumentFileController extends Controller
{
    public function store(UploadLearnerDocumentRequest $request, Learner $learner, LearnerDocument $document): RedirectResponse
    {
        $path = null;
        try {
            DB::transaction(function () use ($request, $learner, $document, &$path) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                $document = LearnerDocument::query()->whereKey($document->id)->where('learner_id', $learner->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $document);
                if ($document->file_url !== null) {
                    throw ValidationException::withMessages(['file' => __('documents.already_attached')]);
                }
                if ($document->status === 'missing' || $document->submitted_on === null) {
                    throw ValidationException::withMessages(['file' => __('documents.record_submission')]);
                }
                $file = $request->file('file');
                // Derive the extension from MIME, never from a private original filename.
                $extension = match ($file->getMimeType()) {
                    'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
                    default => throw ValidationException::withMessages(['file' => __('documents.invalid_file')]),
                };
                $directory = $learner->school_id.'/'.$learner->id;
                $name = Str::uuid().'.'.$extension;
                $path = $directory.'/'.$name;
                if (Storage::disk('learner_documents')->putFileAs($directory, $file, $name, ['visibility' => 'private']) !== $path) {
                    throw new \RuntimeException('Private file write failed');
                }
                $document->file_url = $path;
                $document->save();
                app(AuditLearner::class)->record($actor, $learner, 'document_uploaded');
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                try {
                    if (! Storage::disk('learner_documents')->delete($path)) {
                        throw new \RuntimeException('Private file cleanup failed');
                    }
                } catch (\Throwable $cleanupException) {
                    Log::error('Document upload cleanup failed', ['exception_type' => get_class($cleanupException)]);
                }
            }
            if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException || $exception instanceof ValidationException) {
                throw $exception;
            }
            Log::error('Document upload failed', ['exception_type' => get_class($exception)]);
            throw ValidationException::withMessages(['file' => __('documents.upload_failed')]);
        }

        return to_route('learners.documents.index', $learner)->with('document_uploaded', true);
    }

    public function download(Request $request, Learner $learner, LearnerDocument $document): StreamedResponse
    {
        abort_unless($document->learner_id === $learner->id, 404);
        Gate::authorize('view', $document);
        abort_unless($request->query('user') === $request->user()->id, 403);
        $stream = null;
        try {
            $extension = DB::transaction(function () use ($request, $learner, $document, &$stream) {
                School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $learner = Learner::query()->whereKey($learner->id)->lockForUpdate()->firstOrFail();
                $document = LearnerDocument::query()->whereKey($document->id)->where('learner_id', $learner->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('view', $document);
                $path = $document->file_url;
                // A checklist pointer cannot reference another learner or a public/external URL.
                $prefix = $learner->school_id.'/'.$learner->id.'/';
                abort_unless(is_string($path) && str_starts_with($path, $prefix) && preg_match('/^[a-f0-9-]{36}\.(pdf|jpg|png)$/D', substr($path, strlen($prefix))), 404);
                abort_unless(Storage::disk('learner_documents')->exists($path), 404);
                $stream = Storage::disk('learner_documents')->readStream($path);
                if (! is_resource($stream)) {
                    throw new \RuntimeException('Private file read failed');
                }
                app(AuditLearner::class)->record($actor, $learner, 'document_downloaded');

                return pathinfo($path, PATHINFO_EXTENSION);
            });
        } catch (\Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException || $exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            Log::error('Document download unavailable', ['exception_type' => get_class($exception)]);
            abort(503, __('documents.unavailable'));
        }

        return response()->streamDownload(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 'SMILE-document.'.$extension, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
