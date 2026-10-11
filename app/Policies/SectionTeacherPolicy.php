<?php

namespace App\Policies;

use App\Models\SectionTeacher;
use App\Models\User;

class SectionTeacherPolicy
{
    public function view(User $user, SectionTeacher $assignment): bool
    {
        return $assignment->school_id === $user->school_id && $assignment->section !== null
            && (new SectionPolicy)->view($user, $assignment->section);
    }
}
