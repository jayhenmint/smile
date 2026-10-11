# SMILE: Designer Brief

**Student Monitoring for Inclusive Learning Education**

Prepared: 11 October 2026

Audience: product/UI designer working with the project owner

## 1. What we are designing

SMILE is a teacher-first web application for SNEd (SPED) classes in Philippine public schools. It brings learner records, Individualized Education Programs (IEPs), progress, attendance, health screening, required documents, and school reports into one working tool.

The main user is the SNEd teacher. In the first release, the same teacher is also the school administrator. Prioritize everyday teaching tasks over administrative dashboards.

SMILE works alongside DepEd's Learner Information System (LIS). LIS remains the official enrollment registry; SMILE records information and prepares reports or exports for school use.

This brief describes requirements and a **proposed design structure**. Navigation and tab groupings below are design recommendations, not an approved DepEd form layout. Unresolved school rules must be confirmed before implementation. Planning future screens does not change the build order.

## 2. Current state and delivery order

| Stage | Screens and functions | Current status |
|---|---|---|
| Phase 1: Foundation | Authentication, roles, school settings, teacher accounts, school years, sections/advisers | Built; visual/mobile review still needed |
| Phase 1: Remaining | Learner profiles, guardians, disability records, annual enrollment, roster, document checklist/private uploads, learner audit and encryption | Not built |
| Phase 2: Teaching workflow | IEP builder, progress logging, timeline/trends, review dates, needs-attention list | Not built |
| Phase 3: Daily work and reports | Attendance/SF2, behavior log, health/SF8, printable summaries, class-list Excel export/SF1 reference | Not built |
| Phase 4 | Individualized Transition Plan (ITP), mainstreaming and level-change approvals, consent records | Later |
| Phase 5 | Parent portal, reminders, additional screenings/referrals, SF9 and confirmed SF10 | Later |
| Phase 6 | Multi-school onboarding, configurable report branding, transfers, offline mode, Filipino, analytics, assessment library | Later |

**Phases 1–3 form the first usable release**, to be tested with one teacher. The current dashboard is a starter placeholder; the daily teacher dashboard has not been implemented.

Design the remaining Phase 1 learner and document flows first, then Phase 2, then Phase 3. Produce later-phase concepts separately.

## 3. Users and access

| User | Design scope |
|---|---|
| SNEd teacher (`sped_teacher`) | Learners in their own advised sections; teaching, progress, attendance, health and reports as each phase is built |
| School administrator (`school_admin`) | Own-school setup, teacher accounts, school years, sections; later approval/audit workflows |
| System administrator (`system_admin`) | Technical operations and school onboarding; no routine learner-record browsing |
| Regular teacher | Later: limited accommodations/progress information for mainstreamed learners; exact access needs confirmation |
| Parent | Later: their own child's records only |
| Principal, optional | Later, only if requested: read-only reports/dashboard and applicable sign-off |

A person can hold several roles. Do not require the first-release teacher to maintain separate teacher and administrator accounts. **Adviser is a section assignment, not another role.**

School-wide section setup permission does not give access to every section's learners. Even a teacher who also holds school-admin access must retain learner access restrictions. Co-teacher, aide, and therapist permissions are unresolved; do not imply that an assignment automatically grants learner access.

## 4. Proposed navigation and tabs

Use a desktop sidebar and a compact, accessible mobile navigation. Show the active school-year and section context clearly. Term selection belongs in applicable later workflows; attendance remains daily/monthly.

| Main destination | Contents | Phase |
|---|---|---|
| Dashboard | Today's attendance shortcut, quick progress entry, upcoming IEP reviews, missing documents/needs attention, learner shortcuts | Useful content arrives across 1–3 |
| Learners | Own-section roster, search/filter, add learner, learner detail, annual enrollment | 1 |
| IEP & Progress | IEP list/builder, objective progress, review list, timeline | 2 |
| Attendance | Daily class register, monthly review, SF2 preparation | 3 |
| Behavior | Incident records and follow-up | 3 |
| Health | Measurements, screening results, baseline/endline records | 3 |
| Documents | Required/missing document overview and learner checklist | 1 |
| Reports | Applicable printable reports and exports, preview before output | 3; SF9 later in 5 |
| Settings | Personal settings and authorized school administration | 1 |

Do not expose unfinished destinations as working features. Avoid a crowded sidebar: the designer may consolidate destinations while keeping daily actions easy to reach.

**Suggested learner-detail tabs**, appearing as their phases become available:

| Tab | What belongs here |
|---|---|
| Overview | SMILE ID, optional LRN/status, identity, current enrollment/placement, important next actions |
| Enrollment | School-year history, section, non-graded status, LIS encoding status, transferee information |
| Family & Contacts | Guardians, relationships, primary and emergency contacts |
| IEP | Baseline, goals/objectives, accommodations, services, meetings and review dates |
| Progress | Objective entries, evidence, timeline and trends |
| Attendance | Daily records and monthly summary |
| Health & Support | Disability/diagnostic information, protected health notes, measurements and screening |
| Documents | Checklist, submission/verification status, private attachments |
| Behavior | Incidents and follow-up, if not placed in the timeline |

**Current Settings tabs:** Profile, Password, Appearance; authorized School, Accounts, School years, Sections. Future report branding and school-specific document rules are later work.

## 5. Forms and required content

Field lists describe current implementation or the intended schema. They do not resolve open requiredness, consent, or DepEd-field questions. Do not mark every listed field mandatory.

### Existing foundation forms

| Form | Fields and actions | Design constraints |
|---|---|---|
| Login/authentication | Email, password, remember-me; login, logout, password reset and email-verification flows | No public registration. Accounts are provisioned by authorized administrators. Do not assume verification is currently enforced |
| School settings | School name, optional DepEd school ID, division, region, address; save | School ID is a six-digit text value, preserving leading zeros; own school only |
| Create teacher account | Name, email, initial password, confirmation, optional school-admin access | Always creates a SNEd teacher. Generate password fills both fields; allow manual entry, show/hide and copying. Clear password after submission; no invitation email is currently sent |
| Manage teacher access | School-admin access and active/inactive status | Own account and special-role accounts protected. Deactivation ends sessions; reactivation requires fresh login |
| School year | Label, start date, end date, current-year choice | Dates entered manually; end cannot precede start. At most one current year per school. Selecting a year does not enroll/promote learners |
| Section | School year, name, placement, optional adviser | Non-graded by default. Placement: self-contained SNEd, resource room, regular class. Adviser must be an eligible active same-school SNEd teacher. School year fixed after creation; no delete flow currently |

### Phase 1 learner and document forms

| Form | Content to design |
|---|---|
| Learner identity | First/last name, optional middle name, birthdate, sex, address, mother tongue, optional LRN and LRN status; generated read-only SMILE ID after creation |
| Family/guardian | Guardian name, relationship, phone, email, address; primary-contact and emergency-contact indicators; allow linked guardians |
| Disability/support | Disability category, diagnosis date, assessor/professional, protected notes; optional PWD ID indicator/number and health notes |
| Annual enrollment | School year, section/placement context, default non-graded level, enrollment date, LIS status, transferee flag and previous school |
| Required documents | Applicable requirement, status, submission date, verification information, notes, private attachment and authorized view/download |

Suggested flow: identity → family/support → enrollment → document checklist. The final save/enrollment sequence and mandatory documents need owner confirmation; a multi-step flow is a design proposal, not a requirement to block enrollment.

Document statuses: **Missing, Submitted, Verified, Expired**. Separate “required” from submission status and distinguish optional/not-applicable items. Initial checklist basis includes enrollment form, PSA birth certificate, previous report card for transferees, applicable consent, diagnostic/therapy records, optional PWD ID, prior IEP, assessment/placement records and baseline measurements. School applicability and verification permissions remain to be confirmed.

### Phase 2 IEP and progress forms

The provided IEP sample is **a working reference used by one school, not the current approved IEP form**. Design an improved blank draft for owner/school review; do not label it DepEd-approved.

Suggested builder sections:

1. Learner identity, school year, plan dates and next review.
2. Present levels/baseline: strengths, support needs and family input.
3. Goals and measurable objectives: description, success criteria, target date, evaluation method.
4. Accommodations and modifications.
5. Services: service, provider and frequency; keep service schedule distinct from class placement.
6. Meetings/team decisions and applicable signatures, once confirmed.
7. Review/print summary.

The reference's six domains are Communication, Number, Social, Pre-vocational, Fine Motor, and Perceptual Motor. Domain applicability and objective wording require confirmation; do not force every domain on every learner.

Progress entry: selected objective, date, rating (**Not started / Emerging / Developing / Mastered**), teacher note and optional private evidence. Keep this short enough for use during class. Timeline/trends must show recorded evidence and dates; do not generate a readiness decision.

Possible IEP improvements for review: dated baselines, clear measurable criteria/support level, parent priorities, review reminders, consistent identity headers, and version/amendment history. Approval and amendment workflow is unresolved: schema statuses are draft/active/archived, while the sample uses Draft/Approved/Amended. Do not finalize status controls yet.

### Phase 3 daily forms

| Form | Content and constraints |
|---|---|
| Attendance | Date, school-year/section context, learner list, status (Present, Absent, Late, Excused) and remarks; quick class entry and correction. Show unrecorded separately from present. Exact late/excused/cutting mapping to SF2 needs confirmation |
| Behavior/incident | Learner, date/time, type (Positive, Concern, Incident), trigger, description, strategy used; recorder identity supplied by the application. Detailed follow-up workflow is not yet defined |
| Health measurement | Learner, school year, measurement date, height in cm, weight in kg, period (Baseline, Endline, Other), protected notes; calculated BMI and age/sex-based WHO screening results. Measurer identity supplied by the application; clinician confirmation workflow needs clarification |
| Report preparation | Report type, school year, authorized section/learner, month or term where applicable; preview, print/export |

Do not invent SF8 fields or final incident categories. Use the schema and a confirmed blank form when designing detailed controls.

## 6. Identity, levels and term rules

- **SMILE ID:** automatically generated, permanent, globally unique and read-only. Format `SM-` plus eight random characters from A–Z excluding I/O and digits 2–9. Never ask the teacher to type it. Show it on every printed learner report, including each learner row where applicable.
- **LRN:** optional, exactly 12 digits when supplied, stored/displayed as text. Never generate it. “Issued” requires an LRN; learners without one still use SMILE ID. Statuses: Pending, Issued, Needs correction.
- **Duplicate warning:** same name and birthdate within the school, without LRN, produces a warning rather than a block. Do not expose another school's matches.
- **Non-graded:** new enrollments default to non-graded and learners are re-enrolled each school year. A graded change requires a documented approved level change; no automatic promotion or readiness score.
- **School groupings:** Level 1 = 2 to under 3 years studied in the school; Level 2 = 3 through 4 years inclusive; Level 3 = more than 4 through 6 years. Exactly 3 and exactly 4 years are Level 2. These are separate from graded levels.
- **Transition Program:** owner-confirmed school grouping for ages 15–21. Relationship to duration levels and year-counting rules is unresolved; do not automatically assign a grouping.
- **Reporting:** exactly **Term 1, Term 2, Term 3** for the current three-term requirement. Preserve historical school-year periods; term dates are not yet confirmed.
- **SF9:** exactly three remarks/signature rows, one per term. Teacher remarks and **one parent signature each term: three signatures total**. No fourth term or fourth signature row. This does not define the separate IEP signature requirements.

SMILE does not store the school's own student number. Do not add that field to a mockup.

## 7. Reports and printable forms

| Report | Design basis and required handling | Phase |
|---|---|---|
| SF1 / class register and LIS-matching class list | Supplied non-graded SF1 spreadsheet: school/year/section header, identity/LRN, contacts/address, applicable remarks and male/female/combined totals; field gaps and cutoff rules unresolved | 3 |
| SF2 monthly attendance | Supplied non-graded SF2 spreadsheet: daily grid, learner monthly totals, daily/class totals, monthly summary, applicable remarks and adviser/head signatures | 3 |
| SF8 nutrition | Measurements, BMI and WHO-based screening; approved school layout still needed | 3 |
| Learner progress / IEP summary | SMILE report based on learner evidence and the confirmed IEP template | 3 |
| Class progress / missing documents | Authorized class summary and actionable missing-document list | 3 |
| SF9 non-graded report card | Owner-approved supplied Annex basis; preserve format except confirmed three-term adaptation and SMILE identification | 5 |
| SF10 | Template not confirmed; no final layout yet | 5 |
| ITP / level-change packet | Separate transition plan and human assessment/approval evidence; fields/workflow unconfirmed | 4 |

SF2 remains **monthly**, even with three academic terms. A blank printed presence symbol must come from explicitly recorded presence, never from missing attendance. Summary formulas, school-day calendar, rounding and attendance-code handling need confirmation before finalizing the report.

SF9 uses its own scale: **P (Proficient), AP (Approaching Proficiency), D (Developing), B (Beginning), NO/NA (Not observed/Not applicable)**. It is different from IEP progress ratings; no automatic conversion or averaging is approved. Seven reference domains: Daily Living, Socio-emotional, Language, Cognitive, Aesthetic/Creative, Behavioral, Orientation/Mobility. Applicability and NO/NA handling remain open.

Print requirements:

- A4 and Letter, with landscape grids where the supplied form requires them.
- Print preview, readable text, stable pagination, repeated table headings and no clipped rows.
- SMILE identity and school-specific report heading; plan configurable printed naming/branding without claiming its management UI exists.
- Applicable school, school year, section, reporting month/term and signatures; confirm any new field placement with the school.
- Exclude application navigation/buttons from print output. Avoid hard-coded June–April month headings or invented logos/signatories.
- Review exported PDF and spreadsheet output against blank reference templates, including totals and multi-page cases.

The populated source documents are not safe design assets. Request blank/anonymized references through the owner. The newly supplied `SF9.docx` has not yet been reviewed; do not assume it supersedes the approved Annex basis.

## 8. Interaction, accessibility and visual requirements

- Prioritize mobile and older laptops: fast screens, few clicks, large touch targets and readable tables/cards.
- Keep school-year/section context visible so records are not entered into the wrong class/year.
- Put today's attendance, quick progress and needs-attention shortcuts near the teacher's entry point as those features become available.
- Every input needs a persistent label; provide keyboard navigation, visible focus, sufficient contrast and readable validation messages.
- Status must use text or icons as well as color. Distinguish missing/unrecorded from zero, present, or not applicable.
- English first; allow text expansion for later Filipino translations.
- Reuse the existing Tailwind/shadcn component vocabulary and React/Inertia patterns. The app remains one Laravel project.
- Show save success, validation errors, loading, empty lists, no current year, no section/adviser, protected actions, expired sessions and unavailable attachments.
- Plan clear unsaved-change handling. Do not imply autosave, bulk actions, deletion, offline sync or notifications are implemented.

## 9. Privacy and authorization requirements for design

This application holds children's disability and health records. Use entirely fictional/anonymized content in mockups and prototypes. Do not upload actual learner records or populated references to external design services without explicit owner permission.

Show only authorized schools, sections and learners. A hidden menu item is not the security boundary; the application must authorize every action. Error/denied screens must not reveal restricted learner names, LRNs or health information.

Private attachments need authorized access; do not design public document/photo links. Learner viewing, changes and exports require audit records. Sensitive health/diagnosis/medication fields require encryption. Consent timing needs clarification before finalizing collection flows.

The design must support these five acceptance scenarios (learner features and their tests are still pending):

1. An adviser cannot read or edit another adviser's learner.
2. A user from school A cannot read school B's data.
3. A parent can read only their own child's records.
4. System-admin learner access cannot occur without an audit entry.
5. A non-graded learner cannot become graded without an approved level change.

Health results must say **screening**, not diagnosis. Level-change screens record people's decisions; the system does not determine readiness.

## 10. What the designer should deliver

1. Phase-aware sitemap and teacher/administrator navigation, clearly separating built screens from proposals.
2. Flows for school setup → section/adviser → learner → annual enrollment → document checklist, then IEP → progress → review, then attendance → report.
3. Desktop and mobile wireframes for the roster, learner details, learner/enrollment forms and checklist first.
4. Later wireframes for the teacher dashboard, IEP builder, quick progress, attendance, health and reports.
5. Reusable components: fields, filters, tables/mobile cards, status badges, tabs, date/context selectors, validation, warnings and empty/error states.
6. A clickable prototype with fictional content, keyboard/focus notes and permissions annotated per action.
7. Separate printable layouts using confirmed blank templates, including long names, missing LRNs, large classes and multiple pages.
8. Handoff with field labels, required/optional decisions, responsive behavior and unresolved questions clearly marked.

## 11. Questions to resolve before final design

- Which final blank IEP template will the school accept? Which domains, statuses, participants and signatures apply?
- How are years studied counted: partial years, transfers or interrupted attendance? What happens before two/after six years? Can duration level and Transition Program coexist?
- What are each school year's term dates, attendance calendar and SF1 age/enrollment cutoff?
- How should SF2 late, cutting, excused and other statuses count? Which template sheet, rounding and zero-denominator rules apply?
- SF1 needs reconciliation for district, religion, structured address, mother's maiden name and enrollment-history remarks; which are applicable/required?
- How are SF9 signatures recorded: paper acknowledgment, uploaded signed copy or another approved method? What do With/Without and NO/NA mean in the school's workflow?
- Which blank SF8/SF10 and transition/assessment forms should be used?
- Who can verify documents, approve IEPs or access learner records beyond the adviser? What access do co-teachers/aides/therapists have?
- When and how is consent obtained before data collection? How will parents later get access, and what are the hosting requirements?

Use labeled placeholders for these decisions. Do not invent DepEd requirements or mark an unreviewed draft as approved.

## 12. Repository references

- [PROJECT_SPEC.md](PROJECT_SPEC.md): scope, roles, build order and acceptance checks.
- [PROGRESS.md](PROGRESS.md): actual completed work and outstanding tasks.
- [schema.sql](schema.sql): intended data fields; some reconciliation is still pending.
- [IEP reference](docs/IEP_REFERENCE.md): sample review and improvement proposals.
- [SF1/SF2 reference](docs/SF1_SF2_REFERENCE.md): report mapping and open questions.
- [Curriculum/report reference](docs/CURRICULUM_REPORT_REFERENCE.md): levels, three terms, SF9 and separate ITP basis.

This brief supports design handoff. PROJECT_SPEC.md controls scope and phase order; owner/school confirmation controls unresolved forms and rules.
