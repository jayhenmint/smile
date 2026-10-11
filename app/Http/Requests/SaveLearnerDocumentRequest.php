<?php

namespace App\Http\Requests;

use App\Models\LearnerDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveLearnerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [LearnerDocument::class, $this->route('learner')]);
    }

    public function rules(): array
    {
        $missing = $this->input('status') === 'missing';
        $rules = [
            'status' => ['required', Rule::in(['missing', 'submitted', 'verified', 'expired'])],
            'submitted_on' => $missing ? ['prohibited'] : ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
        foreach (['id', 'school_id', 'learner_id', 'document_type_id', 'verified_by', 'file_url', 'file', 'created_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
