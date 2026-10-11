<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->school_id !== null && ! $user->hasRole('system_admin')
            && $user->hasAnyRole(['school_admin', 'sped_teacher']);
    }

    public function view(User $user, Section $section): bool
    {
        return $this->viewAny($user) && $user->school_id === $section->school_id
            && ($user->hasRole('school_admin') || $section->adviser_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->hasRole('school_admin');
    }

    public function update(User $user, Section $section): bool
    {
        return $this->create($user) && $user->school_id === $section->school_id;
    }

    public function delete(User $user, Section $section): bool
    {
        return false;
    }
}
