<?php

namespace App\Policies;

use App\Models\User;

class DisabilityCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return (new LearnerPolicy)->viewAny($user);
    }
}
