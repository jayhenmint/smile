<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class UploadLearnerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document->learner_id === $this->route('learner')->id && Gate::allows('update', $document);
    }

    public function rules(): array
    {
        $rules = ['file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(config('document_uploads.max_kb')), 'extensions:pdf,jpg,jpeg,png']];
        foreach (['school_id', 'learner_id', 'document_type_id', 'file_url', 'verified_by', 'status', 'submitted_on', 'notes'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
