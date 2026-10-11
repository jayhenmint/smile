<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Learner;
use App\Models\User;
use RuntimeException;

class AuditLearner
{
    public function record(User $actor, Learner $learner, string $event): void
    {
        $entry = activity('learners')->performedOn($learner)->causedBy($actor)->event($event)
            ->tap(fn (ActivityLog $entry) => $entry->school_id = $actor->school_id)
            ->log('learner.'.$event);
        if (! $entry) {
            throw new RuntimeException('Learner auditing is unavailable.');
        }
    }
}
