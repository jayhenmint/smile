<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAssessmentRecordRequest extends StoreAssessmentRecordRequest
{
    public function authorize(): bool
    {
        $learner = $this->route('learner');
        $record = $this->route('assessment') ?? $this->route('disability');

        return $record->learner_id === $learner->id && Gate::allows('view', $learner) && Gate::allows('update', $record);
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'kind' => ['required', Rule::in([$this->route('assessment') ? 'teacher' : 'disability'])],
        ]);
    }
}
