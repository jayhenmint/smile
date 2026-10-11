<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->school_id !== null
            && $user->hasRole('school_admin') && ! $user->hasRole('system_admin');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $account): bool
    {
        return $this->viewAny($user)
            && $user->school_id === $account->school_id
            && $user->id !== $account->id
            && $account->hasRole('sped_teacher')
            && $account->getRoleNames()->diff(['sped_teacher', 'school_admin'])->isEmpty();
    }
}
