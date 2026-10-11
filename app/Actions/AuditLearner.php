<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Learner;
use App\Models\User;
use RuntimeException;

class AuditLearner
{
    private const EVENTS = [
        'created', 'viewed', 'updated',
        'guardians_viewed', 'guardian_created', 'guardian_updated',
        'assessments_viewed', 'teacher_assessment_created', 'disability_record_created',
        'teacher_assessment_updated', 'disability_record_updated', 'lrn_recorded',
        'enrollment_form_viewed', 'enrollment_created', 'documents_viewed',
        'document_checklist_updated', 'document_uploaded', 'document_downloaded',
        'audit_history_viewed',
    ];

    public function record(User $actor, Learner $learner, string $event): void
    {
        // Polymorphic audit keys cannot enforce matching schools through a foreign key.
        // Controllers remain responsible for policy authorization and fresh locked writes.
        if (! $actor->exists || ! $learner->exists || $actor->school_id === null || $actor->school_id !== $learner->school_id || ! in_array($event, self::EVENTS, true)) {
            throw new RuntimeException('Invalid learner audit context.');
        }
        $entry = activity('learners')->performedOn($learner)->causedBy($actor)->event($event)
            ->tap(fn (ActivityLog $entry) => $entry->school_id = $actor->school_id)
            ->log('learner.'.$event);
        if (! $entry) {
            throw new RuntimeException('Learner auditing is unavailable.');
        }
    }
}
