<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->hasRole('system_admin') || $user->school_id !== null);
    }

    public function view(User $user, School $school): bool
    {
        return $user->is_active && ($user->hasRole('system_admin') || $user->school_id === $school->id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole('system_admin');
    }

    public function update(User $user, School $school): bool
    {
        return $user->is_active && ($user->hasRole('system_admin')
            || ($user->hasRole('school_admin') && $user->school_id === $school->id));
    }

    public function delete(User $user, School $school): bool
    {
        return false;
    }
}
