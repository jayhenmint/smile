<?php

namespace App\Http\Requests;

use App\Models\LearnerGuardian;
use Illuminate\Support\Facades\Gate;

class UpdateGuardianRequest extends StoreGuardianRequest
{
    public function authorize(): bool
    {
        $learner = $this->route('learner');
        $guardian = $this->route('guardian');
        $link = LearnerGuardian::query()->where('learner_id', $learner->id)->where('guardian_id', $guardian->id)->first();

        return Gate::allows('view', $learner) && Gate::allows('update', $guardian)
            && $link !== null && Gate::allows('update', $link);
    }
}
