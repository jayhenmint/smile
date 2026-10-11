<?php

namespace App\Policies;

use App\Models\Learner;
use App\Models\TeacherAssessment;
use App\Models\User;

class TeacherAssessmentPolicy
{
    public function create(User $user, Learner $learner): bool
    {
        return (new LearnerPolicy)->view($user, $learner);
    }

    public function view(User $user, TeacherAssessment $record): bool
    {
        return $record->school_id === $user->school_id && $record->learner !== null && (new LearnerPolicy)->view($user, $record->learner);
    }

    public function update(User $user, TeacherAssessment $record): bool
    {
        return false;
    }

    public function delete(User $user, TeacherAssessment $record): bool
    {
        return false;
    }
}
