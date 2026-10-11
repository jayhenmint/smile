<?php

namespace App\Auth;

use App\Models\Scopes\SchoolScope;
use Illuminate\Auth\EloquentUserProvider;

class SchoolUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        // Authentication must find an account before a school context exists.
        // Only auth/remember-token/password-broker lookups get this exception.
        return parent::newModelQuery($model)
            ->withoutGlobalScope(SchoolScope::class)
            ->where('is_active', true);
    }
}
