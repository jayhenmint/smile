<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class SchoolScope implements Scope
{
    public function __construct(
        private readonly string $column = 'school_id',
        private readonly bool $schoolMetadata = false,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($user->hasRole('system_admin')) {
            // Technical operators can manage school metadata, not school-owned records.
            if (! $this->schoolMetadata) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        if ($user->school_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn($this->column), $user->school_id);
    }
}
