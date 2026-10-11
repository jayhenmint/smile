<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreIssuedLrnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('recordLrn', $this->route('learner'));
    }

    public function rules(): array
    {
        $rules = ['lrn' => ['required', 'string', 'regex:/^[0-9]{12}$/D']];
        foreach (['id', 'school_id', 'smile_id', 'lrn_status', 'first_name', 'last_name', 'birthdate', 'health_notes', 'section_id', 'school_year_id', 'level_status', 'grade_level', 'lis_enrollment_status', 'consent_confirmed_by', 'consent_confirmed_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
