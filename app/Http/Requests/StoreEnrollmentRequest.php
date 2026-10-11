<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reenroll', $this->route('learner'));
    }

    public function rules(): array
    {
        $rules = [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $this->user()->school_id)->where('adviser_id', $this->user()->id)],
            'enrolled_on' => ['required', 'date_format:Y-m-d'],
        ];
        foreach (['id', 'learner_id', 'school_id', 'school_year_id', 'smile_id', 'lrn', 'lrn_status', 'level_status', 'grade_level', 'lis_enrollment_status', 'is_transferee', 'previous_school', 'status', 'consent_confirmed_by', 'consent_confirmed_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
