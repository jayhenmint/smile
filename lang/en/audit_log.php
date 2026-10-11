<?php

return [
    'title' => 'Audit log',
    'hint' => 'Read-only activity for learners in your advised sections. Opening this history is also audited. No clinical notes, LRNs or document contents are shown.',
    'empty' => 'No learner activity is available in your advised sections.',
    'learner' => 'SMILE ID', 'actor' => 'Recorded by', 'occurred_at' => 'Date and time',
    'previous' => 'Previous', 'next' => 'Next', 'generic_event' => 'Recorded learner activity',
    'account_unavailable' => 'Account unavailable', 'unavailable' => 'Audit history is temporarily unavailable.',
    'events' => [
        'created' => 'Learner created', 'viewed' => 'Learner viewed', 'updated' => 'Learner details updated',
        'guardians_viewed' => 'Guardian contacts viewed', 'guardian_created' => 'Guardian contact added', 'guardian_updated' => 'Guardian contact updated',
        'assessments_viewed' => 'Assessment records viewed', 'teacher_assessment_created' => 'Teacher assessment added', 'disability_record_created' => 'Disability information added',
        'teacher_assessment_updated' => 'Teacher assessment updated', 'disability_record_updated' => 'Disability information updated',
        'lrn_recorded' => 'Issued LRN recorded', 'enrollment_form_viewed' => 'Annual enrollment form viewed', 'enrollment_created' => 'Annual enrollment added',
        'documents_viewed' => 'Document checklist viewed', 'document_checklist_updated' => 'Document checklist updated', 'document_uploaded' => 'Private attachment uploaded',
        'document_downloaded' => 'Private attachment downloaded', 'audit_history_viewed' => 'Audit history viewed',
    ],
];
