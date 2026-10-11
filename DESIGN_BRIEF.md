# SMILE: Design Requirements

SMILE is a web app for SNEd teachers to manage learners, IEPs, progress, attendance, health, documents and school reports. The first-release teacher is also the school administrator. DepEd LIS remains the official enrollment registry.

## Screens and navigation

| Screen | What to design |
|---|---|
| Login | Email, password, password reset; no public registration |
| Dashboard | Today's attendance, quick progress entry, upcoming reviews and needs attention |
| Learners | Searchable class roster, add learner, learner details and annual enrollment |
| IEP & Progress | Baselines, goals/objectives, accommodations, services, progress entries and timeline |
| Attendance | Daily class register, monthly summary and SF2 |
| Behavior | Positive observations, concerns, incidents and strategies used |
| Health | Height/weight, baseline/endline measurements, calculated BMI and screening results |
| Documents | Required-document checklist, missing/submitted/verified/expired status and private attachments |
| Reports | Report selection, preview, print and Excel export |
| Settings | Profile, Password, Appearance, School, Accounts, School years, Sections |

**Suggested learner tabs:** Overview, Enrollment, Family & Contacts, IEP, Progress, Attendance, Health & Support, Documents, Behavior. Navigation and tab groupings are proposals; show features only when implemented.

## Forms and essential fields

| Form | Fields |
|---|---|
| Learner | Name, birthdate, sex, address, mother tongue, optional LRN/status; generated read-only SMILE ID |
| Guardian | Name, relationship, phone/email, address, primary/emergency contact |
| Disability/support | Category, assessment date/professional, protected notes, optional PWD ID |
| Enrollment | School year, section, placement, non-graded status, enrollment date, LIS status, transferee/previous school |
| Document | Type, status, submission date, verification information, notes, private file |
| IEP | Dates/review date, baseline, goals, measurable objectives/criteria, accommodations, services/provider/frequency, team decisions |
| Progress | Objective, date, rating, note, optional evidence; ratings: Not started, Emerging, Developing, Mastered |
| Attendance | Date, class, learner, Present/Absent/Late/Excused, remarks; distinguish unrecorded from present |
| Behavior | Learner, date/time, Positive/Concern/Incident, trigger, description, strategy |
| Health | Learner/year, date, height (cm), weight (kg), Baseline/Endline/Other, notes; BMI calculated |
| School | Name, optional six-digit DepEd school ID, division, region, address |
| Teacher account | Name, email, password/confirmation, Generate password, optional school-admin access; activation controls for eligible existing accounts |
| School year | Label, start/end dates, current-year selection |
| Section | School year, name, placement, optional eligible adviser; defaults to non-graded |

Confirm field requiredness and school-specific document requirements before finalizing forms.

Non-graded enrollment may proceed during the school year with pending documents/assessment. Show follow-up items; teacher-accepted documents may arrive before year-end. Teachers may assess through observations/tests, with medical records as supporting evidence. Require teacher confirmation that parental consent is already obtained; the signed copy may follow.

## Reports

- **Phase 3:** SF1/class list, monthly SF2, SF8, learner progress, IEP summary, class progress and missing documents.
- **Later:** SF9 (Phase 5), SF10 after its template is confirmed, and transition/approval packets (Phase 4).
- Use the supplied blank/anonymized SF1/SF2 and approved Annex SF9 basis. SF8/SF10 layouts still need confirmation.
- IEP sample is a school reference, **not the current approved IEP form**. Propose an improved draft for school review.
- Print on A4/Letter with appropriate orientation, readable tables, repeated headers and no clipped rows. Include school/year/class context and SMILE ID for each learner.

## Rules the design must preserve

- SMILE ID is generated, permanent and read-only: `SM-` plus eight random allowed characters. No school student-number field.
- LRN is optional, exactly 12 digits when supplied; never generate it. Issued status requires an LRN. Duplicate name/birthdate without LRN gives a warning, not a block.
- Learners default to non-graded and re-enroll yearly. Graded changes require documented approval; never auto-promote or decide readiness.
- School groupings: Level 1 = 2 to under 3 years studied; Level 2 = 3 through 4 inclusive; Level 3 = more than 4 through 6. Transition Program = ages 15–21. Counting/assignment rules need confirmation.
- **Exactly three terms: Term 1, Term 2, Term 3. SF9 requires teacher remarks and one parent signature each term: three signatures total.** IEP signatures are a separate unresolved requirement.
- SF9 ratings differ from IEP progress: P, AP, D, B, NO/NA. Do not automatically convert or average them.
- Health results use age/sex-based WHO screening standards; label them screening, not diagnosis.

## Design requirements

- Mobile-friendly, fast, few clicks, large touch targets; visible school-year/section context.
- Labeled inputs, keyboard access, visible focus, sufficient contrast; never use color alone for status.
- English first; reuse existing Tailwind/shadcn components.
- Include loading, empty, validation, duplicate-warning, saved, access-denied and expired-session states.
- Adviser sees own-section learners; parent later sees only their child. School-admin setup access does not grant access to every learner. System admin has no routine learner access.
- Use fictional content only. Health data and attachments are private; learner access/changes/exports are audited. Do not upload populated learner documents to design services without owner permission.

## Priority and designer deliverables

**Built:** authentication, school/account/year/section settings. Dashboard is currently a placeholder.

**Design next, in order:** Phase 1 learner/enrollment/documents → Phase 2 IEP/progress → Phase 3 daily work/reports. Parent portal and transition workflows are later.

Deliver a sitemap, desktop/mobile layouts, key form/error states, a clickable prototype and print layouts using confirmed blank templates.

**Confirm before final layouts:** IEP approval/signatures/domains, term dates, school-year counting, SF1 field gaps, SF2 codes/calendar/totals, SF9 signature capture and NO/NA meaning, SF8/SF10 templates, consent timing and additional staff access. Do not invent DepEd rules or label drafts approved.

Source of truth: [PROJECT_SPEC.md](PROJECT_SPEC.md). Current build status: [PROGRESS.md](PROGRESS.md). Form details: [IEP](docs/IEP_REFERENCE.md), [SF1/SF2](docs/SF1_SF2_REFERENCE.md), [curriculum/SF9](docs/CURRICULUM_REPORT_REFERENCE.md).
