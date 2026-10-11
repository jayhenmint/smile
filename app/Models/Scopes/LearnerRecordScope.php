<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class LearnerRecordScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // The related learner has both school and adviser access scopes.
        $builder->whereHas('learner');
    }
}
