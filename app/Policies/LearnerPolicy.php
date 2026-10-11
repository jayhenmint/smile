<?php

namespace App\Policies;

use App\Models\Learner;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LearnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->school_id !== null && $user->hasRole('sped_teacher') && ! $user->hasRole('system_admin');
    }

    public function create(User $user, Section $section): bool
    {
        return $this->viewAny($user) && $section->school_id === $user->school_id && $section->adviser_id === $user->id;
    }

    public function view(User $user, Learner $learner): bool
    {
        return $this->viewAny($user) && $learner->school_id === $user->school_id
            && DB::table('enrollments')->join('sections', 'sections.id', '=', 'enrollments.section_id')
                ->where('enrollments.school_id', $user->school_id)->where('sections.school_id', $user->school_id)
                ->where('enrollments.learner_id', $learner->id)->where('sections.adviser_id', $user->id)->exists();
    }

    public function update(User $user, Learner $learner): bool
    {
        return $this->view($user, $learner);
    }

    public function recordLrn(User $user, Learner $learner): bool
    {
        return $this->update($user, $learner) && $learner->lrn === null && $learner->lrn_status === 'pending';
    }

    public function reenroll(User $user, Learner $learner): bool
    {
        return $this->view($user, $learner) && $learner->status === 'enrolled';
    }

    public function delete(User $user, Learner $learner): bool
    {
        return false;
    }
}
