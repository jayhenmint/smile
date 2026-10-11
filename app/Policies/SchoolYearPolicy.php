<?php

namespace App\Policies;

use App\Models\SchoolYear;
use App\Models\User;

class SchoolYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->school_id !== null
            && ! $user->hasRole('system_admin') && $user->hasAnyRole(['school_admin', 'sped_teacher']);
    }

    public function view(User $user, SchoolYear $schoolYear): bool
    {
        return $this->viewAny($user) && $user->school_id === $schoolYear->school_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->hasRole('school_admin');
    }

    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return $this->create($user) && $user->school_id === $schoolYear->school_id;
    }

    public function delete(User $user, SchoolYear $schoolYear): bool
    {
        return false;
    }
}
