# SMILE IEP reference and review

Reviewed on 2026-10-11 against the owner-supplied `IEP SAMPLE.docx` in the repository root, `PROJECT_SPEC.md`, and the IEP tables in `schema.sql`.

## Basis and scope

The owner has designated this sample as the basis for SMILE's IEP builder and printable IEP. Follow its section order, domain labels, and school-specific wording when Phase 2 begins. PROJECT_SPEC.md still controls build order, security, SMILE ID, optional LRN, and non-graded enrollment rules. Reviewing this reference does not start Phase 2.

The original contains filled-in names and health information of uncertain authenticity. Keep it local and ignored by Git; do not copy its narrative, contact details, images, or document metadata into fixtures, seeds, screenshots, or public documentation. Use a blank or anonymized copy before committing a printable template. The Word temporary lock file is also ignored.

This review examined document text, table cells, and header/footer text. It did not visually render the pages or inspect embedded images, so page layout, logos, spacing, and print fidelity remain unverified. The original has not been edited.

## Sections to preserve

The owner confirmed on 2026-10-11 that each learner's IEP remains separate from their ITP. See `CURRICULUM_REPORT_REFERENCE.md` for the approved report-card basis, school groupings, and three-term reporting clarification. Its seven report-card domains and rating scale do not replace this sample's six IEP domains or the IEP progress scale. This clarification does not resolve the sample's remaining approval, label, schedule, or signature questions.

1. School heading and IEP status: Draft, Approved, Amended.
2. Learner and school information: learner name, level, LRN, address, guardian, guardian address, contact number.
3. IEP meeting date, most recent evaluation date, projected annual review date, case manager, and special needs/difficulties.
4. IEP team participants: principal, guidance coordinator, general education teacher, SNEd teacher/IEP chair.
5. Present level of performance by domain: Communication Skills, Number Skills, Social Skills, Pre-vocational Skills, Fine Motor Skills, Perceptual Motor Skills.
6. Parent input, special considerations/accommodations, learner preferences and interests.
7. Goals by the same six domains, each with an evaluation method.
8. Services and placement choices, including self-contained class and transition/mainstreaming/inclusion options.
9. Placement decision, supporting documents/evidence, time and days in the SNEd class.
10. Authorization wording, date, and printed-name/signature blocks for guardian, teacher/case manager, guidance coordinator, and principal.

Preserve the sample's domain labels initially. Any additional domains should be configurable and confirmed with the owner. The app records decisions made by the team; it must not infer readiness from goals or service selections.

## Proposed improvements

These are suggestions for review, not newly approved DepEd requirements.

- Replace all filled example narratives with blank fields or clearly fictional, consistent examples. Resolve mixed names and pronouns before using this form for another learner.
- Correct spelling, repeated status text, stray text fragments, and inconsistent placeholder names. Confirm whether "PROJECTED IEP ANNNUAL PREVIEW DATE" means annual review date before changing the printed label.
- Populate the learner header from the learner record. Add the generated SMILE ID to every printout, keep LRN optional, and show non-graded placement unless an approved level change exists.
- Capture baseline performance separately for each of the six domains, with strengths, support needs, and dated evidence where the teacher provides it. Do not generate conclusions or reuse another learner's narrative.
- Support measurable objectives under each annual goal: observable skill, target date, success criterion, assessment method, and assistance level where applicable. These are proposed form extensions; confirm required columns with the school before printing them.
- Record evaluation methods per goal/objective rather than automatically repeating one method everywhere. Use actual dated progress entries as supporting evidence.
- Capture parent input and learner preferences explicitly. Distinguish an empty response from "not assessed" or "not applicable" instead of interpreting "none" as a finding.
- Make accommodation choices editable and allow explanatory notes. Avoid preselecting the sample's checked choices for every learner.
- Separate services, proposed placement options, and the team's selected placement. Flag conflicting selections for human review; never choose placement automatically.
- Separate time-of-day and days-of-week fields. The sample describes a five-day service while its placement schedule lists three days; confirm the intended schedule instead of copying both as defaults.
- Make school heading/footer details configurable and include IEP version, school year, and review date. Preserve approved print wording while keeping A4/Letter print verification as an acceptance check.
- Record participants and signature dates individually. Parent authorization wording in this sample must be reviewed with the school before treating it as sufficient data privacy or other consent; do not expand who can see records based on this wording.

## Schema comparison for Phase 2

| Sample content | Current schema support | Work to resolve before implementation |
|---|---|---|
| Learner/guardian identity and school heading | Learners, guardians, schools, school years | Reuse related records and add SMILE ID to print; confirm which guardian/contact is shown. |
| Draft / Approved / Amended | `ieps.status`: draft / active / archived | Confirm approval, amendment, and archival semantics. Do not silently map Approved to active or Amended to archived. |
| Six domain baselines | `ieps.present_levels` is one text field | Decide how to preserve separate domain responses and print order. |
| Parent input and learner interests | No dedicated IEP fields | Plan explicit storage after owner confirmation. |
| Goals and evaluation methods | `iep_goals` has domain, description, target date | Evaluation method has no dedicated field. Confirm goal versus objective ownership. |
| Measurable objectives | `iep_objectives` has description, criteria, target date | Confirm desired printed structure; the sample does not provide a separate objective grid. |
| Accommodation checklist | `ieps.accommodations` and `modifications` are text | Preserve checklist choices and notes without hardcoded checked defaults. |
| Services / placement / class schedule | `iep_services` has service, provider, frequency; placement exists elsewhere | Confirm service versus placement meaning and whether each IEP must retain a historical placement/schedule snapshot. |
| Meeting, evaluation, annual review dates | `iep_meetings.held_on`, `ieps.start_date`, `next_review_date` | Most recent evaluation date has no dedicated IEP field; start date and meeting date are not interchangeable. |
| Team roles, authorization and signatures | Meeting attendees/minutes/decisions; `parent_signed_on` | Confirm how each participant, approval, signature date, and amendment version is recorded. |
| School isolation and sensitive narrative | IEP child tables currently lack explicit `school_id` | Reconcile school scoping, policies, encrypted sensitive content, private attachments, and audit events before migrations. |

Do not change schema.sql solely to settle these unresolved choices. When Phase 2 migrations are designed, update migrations and schema.sql together with the confirmed reasons.

## Questions for the owner/school

1. Is this the current approved school/division form, or a worked example supplied as guidance? Are its images and narrative fictional or anonymized?
2. Should the annual date label read "annual review"? Which labels and exact authorization wording must remain unchanged?
3. Who approves an IEP, when does it become active, and how should amendments and re-signing work?
4. Are the six listed domains mandatory for every learner, and may the teacher add domains?
5. Should measurable objectives be printed under each goal, or retained only in the app's progress workflow?
6. Which service/placement choices can coexist, and what are the correct class days and hours?
7. Which participants/signatures are required? The participant list includes a general education teacher, while the signature block has four other participant roles.

## Workflow when Phase 2 begins

1. Re-read this review and the local sample; obtain answers to the form questions and a blank/anonymized template.
2. Confirm the IEP data and version model, then reconcile schema.sql and migrations together.
3. Build the six-domain baseline, parent input/preferences, goals/objectives/evaluation methods, accommodations, and services in small tested steps.
4. Record meeting, approval/amendment, and placement decisions made by people, with the confirmed signature workflow.
5. Generate the school-approved printable view from the same records and verify page layout in A4 and Letter.
6. Verify school/section authorization, encryption of sensitive content, private evidence files, view/edit/export auditing, and SMILE ID on the report before declaring the IEP step done.
