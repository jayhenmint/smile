<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class AdvisedEnrollmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        if (! $user || ! $user->is_active || $user->hasRole('system_admin') || ! $user->hasRole('sped_teacher')) {
            $builder->whereRaw('1 = 0');

            return;
        }
        $builder->whereExists(fn ($query) => $query->selectRaw('1')->from('sections')
            ->whereColumn('sections.id', $model->qualifyColumn('section_id'))
            ->whereColumn('sections.school_id', $model->qualifyColumn('school_id'))
            ->where('sections.adviser_id', $user->id));
    }
}
