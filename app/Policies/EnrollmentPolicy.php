<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;

class EnrollmentPolicy
{
    public function create(User $user, Section $section): bool
    {
        return (new LearnerPolicy)->create($user, $section);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return (new LearnerPolicy)->viewAny($user) && $enrollment->school_id === $user->school_id
            && $enrollment->section?->adviser_id === $user->id;
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return false;
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return false;
    }
}
