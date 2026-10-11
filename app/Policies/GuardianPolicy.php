<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\Learner;
use App\Models\User;

class GuardianPolicy
{
    public function create(User $user, Learner $learner): bool
    {
        return (new LearnerPolicy)->view($user, $learner);
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return (new LearnerPolicy)->viewAny($user) && $guardian->school_id === $user->school_id
            && $guardian->links()->whereHas('learner', fn ($query) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->whereHas('section', fn ($sections) => $sections->where('adviser_id', $user->id))))->exists();
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return false;
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return false;
    }
}
