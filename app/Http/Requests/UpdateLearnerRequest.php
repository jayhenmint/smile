<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('learner'));
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'], 'birthdate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sex' => ['nullable', Rule::in(['M', 'F'])], 'address' => ['nullable', 'string', 'max:2000'],
            'mother_tongue' => ['nullable', 'string', 'max:255'], 'health_notes' => ['nullable', 'string', 'max:5000'],
        ];
        foreach (['id', 'school_id', 'smile_id', 'lrn', 'lrn_status', 'status', 'school_year_id', 'section_id', 'grade_level', 'level_status', 'lis_enrollment_status', 'enrolled_on', 'is_transferee', 'previous_school', 'consent_confirmed', 'consent_confirmed_by', 'consent_confirmed_at', 'photo_url', 'has_pwd_id', 'pwd_id_number'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
