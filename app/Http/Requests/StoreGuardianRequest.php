<?php

namespace App\Http\Requests;

use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [Guardian::class, $this->route('learner')]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['required', 'boolean'], 'is_emergency' => ['required', 'boolean'],
            'school_id' => ['prohibited'], 'user_id' => ['prohibited'], 'learner_id' => ['prohibited'], 'guardian_id' => ['prohibited'], 'id' => ['prohibited'],
        ];
    }
}
