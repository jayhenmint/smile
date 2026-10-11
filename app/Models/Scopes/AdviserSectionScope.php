<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class AdviserSectionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        if (! $user || ! $user->is_active || $user->hasRole('system_admin')) {
            $builder->whereRaw('1 = 0');
        } elseif ($user->hasRole('school_admin')) {
            // Section setup metadata only; this does not grant learner access.
        } elseif ($user->hasRole('sped_teacher')) {
            $builder->where($model->qualifyColumn('adviser_id'), $user->id);
        } else {
            $builder->whereRaw('1 = 0');
        }
    }
}
