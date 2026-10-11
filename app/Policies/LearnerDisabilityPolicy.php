<?php

namespace App\Policies;

use App\Models\Learner;
use App\Models\LearnerDisability;
use App\Models\User;

class LearnerDisabilityPolicy
{
    public function create(User $user, Learner $learner): bool
    {
        return (new LearnerPolicy)->view($user, $learner);
    }

    public function view(User $user, LearnerDisability $record): bool
    {
        return $record->school_id === $user->school_id && $record->learner !== null && (new LearnerPolicy)->view($user, $record->learner);
    }

    public function update(User $user, LearnerDisability $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, LearnerDisability $record): bool
    {
        return false;
    }
}
