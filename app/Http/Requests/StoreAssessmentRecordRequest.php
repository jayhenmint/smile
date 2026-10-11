<?php

namespace App\Http\Requests;

use App\Models\LearnerDisability;
use App\Models\TeacherAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssessmentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [$this->input('kind') === 'teacher' ? TeacherAssessment::class : LearnerDisability::class, $this->route('learner')]);
    }

    public function rules(): array
    {
        $teacher = $this->input('kind') === 'teacher';

        return [
            'kind' => ['required', Rule::in(['teacher', 'disability'])],
            'assessed_on' => [$teacher ? 'required' : 'prohibited', 'date_format:Y-m-d', 'before_or_equal:'.now(config('app.school_timezone'))->toDateString()],
            'observations' => [$teacher ? 'required' : 'prohibited', 'string', 'max:10000'],
            'category_id' => [$teacher ? 'prohibited' : 'required', 'integer', Rule::exists('disability_categories', 'id')],
            'diagnosis_date' => [$teacher ? 'prohibited' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now(config('app.school_timezone'))->toDateString()],
            'assessed_by' => [$teacher ? 'prohibited' : 'nullable', 'string', 'max:255'],
            'notes' => [$teacher ? 'prohibited' : 'nullable', 'string', 'max:10000'],
            'id' => ['prohibited'], 'school_id' => ['prohibited'], 'learner_id' => ['prohibited'], 'recorded_by' => ['prohibited'], 'created_at' => ['prohibited'],
        ];
    }
}
