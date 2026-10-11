# Phase 1 authorization review

Reviewed against the implemented learner routes on 2026-10-12. This records current coverage; it is not production release approval.

| Required check | Current evidence | Remaining work |
|---|---|---|
| Advisers cannot read/edit another adviser's learners | Learner creation/profile, contacts, assessment corrections, LRN, enrollment and document tests reject other advisers. Audit rows/counts also exclude them. | Recheck every new feature. |
| School A cannot read school B | Global school scopes, composite ownership constraints and feature tests cover current school-owned models/routes, including audits and signed files. | Apply the same rules to future tables. |
| Parents read only their own child | Parent accounts are denied all current learner routes. | Own-child reading is not implemented; its positive/negative release tests require the later approved parent-access workflow. |
| System admins cannot open learner records without an audit | Routine access is denied even with extra teacher/admin roles. Valid teacher views/downloads fail closed when auditing fails or is disabled. | Exceptional operator access is not implemented. Any future exception needs its own policy, reason and audit tests. |
| Non-graded learners cannot become graded without approval | Creation/enrollment inputs cannot override the non-graded default; database guards reject direct graded updates. | Approved level changes and their positive-path tests belong to Phase 4. |

The complete five-check release requirement is not claimed finished. Phase 1 coverage verifies the routes that currently exist.

Current learner activity is audited for creation, roster/detail/edit views, identity corrections, guardian/contact views and changes, teacher/disability assessment views and changes, issued LRN recording, enrollment form views and saves, checklist views and changes, attachment uploads/downloads and audit-history views. Viewing an empty creation form does not expose existing learner records. Report exports and deletion are not implemented.

The shared audit helper rejects mismatched actor/learner schools, unsaved subjects/actors and unsupported event descriptions. Controllers still enforce policies; this helper does not grant access. Audit rows store identifiers and static event descriptions, with no learner names, LRNs or clinical attributes.

The owner confirmed that **Settings > Audit log** is read-only and limited to the teacher/admin's own advised learners. Both school_admin and sped_teacher are required. The model adds a learner-access scope, and each displayed entry receives a policy check. The page returns only action, SMILE ID, recording account and time; viewing the page is audited once per distinct displayed learner. Reassignment removes old-adviser access. There are no audit export, edit or delete endpoints; retention/purge rules remain unconfirmed.

Primary evidence: `LearnerCreationTest`, `LearnerProfileTest`, the guardian/assessment/LRN/enrollment/document feature suites, `LearnerAuditIntegrityTest` and `Settings/AuditLogTest`. Vitest document-flow tests also verify upload/checklist interactions and accessible errors; backend tests enforce authorization. Browser/mobile review remains pending; production hosting, HTTPS, backups and storage also require preparation. Existing npm dependency advisories require a separate tested cleanup stage.
