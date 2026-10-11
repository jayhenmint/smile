<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    // No audit browsing/editing UI is enabled in this stage.
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ActivityLog $entry): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $entry): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $entry): bool
    {
        return false;
    }
}
