<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope('learner_access', fn (Builder $query) => $query->whereHasMorph('subject', [Learner::class]));
    }
}
