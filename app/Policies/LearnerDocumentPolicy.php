<?php

namespace App\Policies;

use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\User;

class LearnerDocumentPolicy
{
    public function create(User $user, Learner $learner): bool
    {
        return (new LearnerPolicy)->update($user, $learner);
    }

    public function view(User $user, LearnerDocument $document): bool
    {
        return $document->school_id === $user->school_id && $document->learner !== null && (new LearnerPolicy)->view($user, $document->learner);
    }

    public function update(User $user, LearnerDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, LearnerDocument $document): bool
    {
        return false;
    }
}
