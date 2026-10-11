<?php

namespace App\Policies;

use App\Models\LearnerGuardian;
use App\Models\User;

class LearnerGuardianPolicy
{
    public function view(User $user, LearnerGuardian $link): bool
    {
        return $link->school_id === $user->school_id && $link->learner !== null && (new LearnerPolicy)->view($user, $link->learner);
    }

    public function update(User $user, LearnerGuardian $link): bool
    {
        return $this->view($user, $link);
    }

    public function delete(User $user, LearnerGuardian $link): bool
    {
        return false;
    }
}
