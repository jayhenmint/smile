<?php

namespace App\Http\Requests;

use App\Models\Learner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Learner::class);
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('app.school_timezone'))->toDateString()],
            'sex' => ['nullable', Rule::in(['M', 'F'])],
            'address' => ['nullable', 'string', 'max:2000'],
            'mother_tongue' => ['nullable', 'string', 'max:255'],
            'health_notes' => ['nullable', 'string', 'max:5000'],
            'lrn' => ['nullable', 'string', 'regex:/^[0-9]{12}$/', 'required_if:lrn_status,issued'],
            'lrn_status' => ['required', Rule::in(['pending', 'issued', 'needs_correction'])],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $this->user()->school_id)->where('adviser_id', $this->user()->id)],
            'enrolled_on' => ['required', 'date_format:Y-m-d'],
            'is_transferee' => ['required', 'boolean'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'consent_confirmed' => ['required', 'accepted'],
        ];
        foreach (['id', 'school_id', 'school_year_id', 'smile_id', 'grade_level', 'level_status', 'lis_enrollment_status', 'consent_confirmed_by', 'consent_confirmed_at', 'status', 'photo_url'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['lrn.unique' => __('learners.lrn_unavailable'), 'consent_confirmed.accepted' => __('learners.consent_required')];
    }
}
