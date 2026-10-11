<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
    }
}
