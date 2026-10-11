<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\Learner;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('school_admin') && (new LearnerPolicy)->viewAny($user);
    }

    public function view(User $user, ActivityLog $entry): bool
    {
        return $this->viewAny($user) && $entry->school_id === $user->school_id && $entry->subject instanceof Learner
            && (new LearnerPolicy)->view($user, $entry->subject);
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
