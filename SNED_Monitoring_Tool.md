# SMILE: Student Monitoring for Inclusive Learning Education (Planning Summary)

> Draft based on general knowledge of DepEd policy (RA 11650 Inclusive Education Act, LIS, SF2/SF9/SF10). Confirm exact forms and requirements with your school head, SPED coordinator, and division office before launch.

## 1. Goal

Help SNED (SPED) students, teachers, and parents by:
- Tracking each learner's progress against their IEP
- Helping teachers manage the class (attendance, behavior, reviews)
- Keeping the documents DepEd and the school require for enrollment and for moving a student to a regular class

---

## 2. Phase 1 (Build Now): MVP Checklist

### Learner profile
- [ ] LRN, full name, birthdate, sex, address, grade level, section
- [ ] Disability category (learning, intellectual, autism spectrum, hearing, visual, physical, speech/language, emotional-behavioral, multiple)
- [ ] Parent/guardian and emergency contacts
- [ ] Diagnosis date and assessing professional
- [ ] Placement: SPED self-contained, resource room, or regular class with support
- [ ] Health notes (allergies, medication)

### IEP (Individualized Education Plan) tracker
- [ ] Present levels of performance (baseline)
- [ ] Annual goals and short-term objectives
- [ ] Accommodations and modifications
- [ ] Support services (speech, OT, counseling) and providers
- [ ] Review dates, meeting records, parent sign-off

### Progress monitoring
- [ ] Rating per objective: Not started / Emerging / Developing / Mastered
- [ ] Date, teacher note, optional evidence (photo/file)
- [ ] Trend view per student
- [ ] Printable quarterly summary for parents

### Attendance and behavior
- [ ] Daily attendance (SF2-compatible)
- [ ] Behavior/incident log with triggers and strategies that worked

### Teacher tools
- [ ] Class roster with filters (disability, placement)
- [ ] "Needs attention" list (overdue IEP reviews, no progress entry in 30 days, long absences)
- [ ] Printable reports

### Security and compliance (from day one)
- [ ] Role-based access: admin, SPED teacher, regular teacher (limited), parent (own child only)
- [ ] Audit log of views and edits
- [ ] Data Privacy Act (RA 10173): parental consent, encrypted sensitive data
- [ ] Backups and export

---

## 3. Phase 2 (Add Later)

- Parent portal with messaging and notifications
- Auto-generated SF9, SF10, and LIS export
- Therapy/service session scheduling and logs
- Transition (mainstreaming) planning with readiness indicators
- Assessment tools and rubric library
- Offline mode (PWA) for low connectivity
- Filipino/English interface and accessibility features
- Analytics for the principal or division office

---

## 4. Required Documents

### Enrollment: from parents/guardian
- [ ] Basic Education Enrollment Form
- [ ] PSA birth certificate (or school-accepted alternative)
- [ ] Previous report card (SF9) / learner records (SF10) for transferees
- [ ] Recent ID photos, if required
- [ ] Parental consent forms (enrollment, data privacy, photo/media, services)

### Enrollment: disability-specific
- [ ] Medical/diagnostic report from a qualified professional (developmental pediatrician, neurologist, psychologist)
- [ ] Therapy or intervention records, if any
- [ ] PWD ID (helpful, usually not mandatory)
- [ ] Medication and health information
- [ ] Prior IEP, if any

### Enrollment: from the school
- [ ] LIS enrollment record and LRN
- [ ] Initial assessment/screening results
- [ ] New or updated IEP
- [ ] Placement decision record

### Moving to a regular class (mainstreaming): from parents
- [ ] Written request letter
- [ ] Signed consent and acknowledgment of the transition plan
- [ ] Updated medical/psychological assessment, if the school asks

### Moving to a regular class: from the school (SPED/IEP team)
- [ ] Updated IEP or progress summary showing readiness
- [ ] SPED teacher's recommendation
- [ ] IEP team meeting record with parents
- [ ] Transition plan (gradual hours, accommodations, review dates)
- [ ] Notice to the receiving regular teacher
- [ ] Follow-up monitoring (e.g., review after 1-2 quarters) with a path back to SPED support

---

## 5. Database

The full PostgreSQL schema is in `schema.sql`. Main tables:

| Area | Tables |
|---|---|
| Access | `users` (roles), `audit_log`, `consents` |
| School structure | `school_years`, `sections`, `enrollments` |
| Learners | `learners`, `guardians`, `learner_guardians`, `learner_disabilities`, `disability_categories` |
| Documents | `document_types`, `learner_documents` |
| IEP | `ieps`, `iep_goals`, `iep_objectives`, `iep_services`, `iep_meetings` |
| Progress | `progress_entries` |
| Daily tracking | `attendance`, `behavior_logs` |
| Mainstreaming | `transition_plans` |
| Views | `needs_attention` |

---

## 6. Chosen Tech Stack

| Layer | Choice |
|---|---|
| Frontend | React + TypeScript, Tailwind CSS, shadcn/ui |
| Backend | Laravel (REST API) |
| Database | PostgreSQL |

### Recommended additions
| Need | Suggestion |
|---|---|
| Auth | Laravel Sanctum (SPA cookie auth) |
| Roles/permissions | `spatie/laravel-permission` plus Laravel Policies |
| Audit trail | `spatie/laravel-activitylog` |
| Data fetching (React) | TanStack Query, React Hook Form + Zod |
| Charts | Recharts |
| PDF reports | `barryvdh/laravel-dompdf` |
| Excel/CSV export | `maatwebsite/excel` |
| File storage | Laravel Filesystem with S3-compatible storage (private bucket, signed URLs) |
| Queues/reminders | Laravel Queues and Scheduler (IEP review reminders) |
| Encryption | Laravel `encrypted` model casts for health/diagnosis fields |
| Testing | Pest/PHPUnit (backend), Vitest (frontend) |

---

## 7. Suggested Build Order

1. Auth, roles, learner profile, document checklist
2. IEP builder and progress logging
3. Attendance, needs-attention dashboard, printable reports
4. Parent portal, DepEd form exports, transition plans

## 8. Updates After Review (v2)

### 8.1 SMILE ID and LRN
- `learners.smile_id`: SMILE's own learner ID, generated by the tool (for example `SM-7K3QX9PD`). It is globally unique, permanent, never reused, and not derived from name, birthdate, or LRN. The school's own student number is not used or stored.
- `learners.lrn`: DepEd's 12-digit Learner Reference Number. It is **optional**, unique when present, and issued through the LIS, so the tool stores it and does not generate it.
- `lrn_status` (`pending`, `issued`, `needs_correction`) defaults to `pending`. `issued` requires an LRN.
- A learner with no LRN is still fully identified by the SMILE ID. The app warns about possible duplicates (same name and birthdate) when no LRN is given.

### 8.2 Non-graded SNEd learners
- A SNEd learner is **non-graded** and is re-enrolled every school year in a non-graded class. `enrollments.level_status` defaults to `non_graded`, and `grade_level` stays empty until a formal change.
- Moving a learner to a graded level needs the learner's **SPED assessment form** and **Schools Division Office (SDO) approval**, based on LIS guidance. The tool tracks this in `learner_level_changes` (form file, SDO status, decision date, LIS updated or not).
- Who decides readiness, and when, is a school and division matter. The tool records the evidence (IEP progress, teacher recommendation, assessment) and does not decide anything itself.
- LIS enrollment also requires SPED assessment before a SNEd learner is enrolled. `lis_enrollment_status` tracks the encoding.

### 8.3 Health and nutrition
- `health_measurements`: height, weight, auto-calculated BMI, baseline and endline per school year.
- `bmi_category` and `hfa_category` (nutritional status by age and sex, WHO standards) are computed in the app from the WHO tables.
- `health_screenings`: vision, hearing, dental, physical fitness, with referral and follow-up.
- `feeding_program`: flags SBFP beneficiaries.
- DepEd division memos describe this as BMI plus height-for-age assessment, reported via SF8 or the DepEd BMI software.

### 8.4 Multi-school adoption
- `schools` table, with `school_id` on users, school years, sections, and learners.
- Adopting schools get isolated data: Laravel global scope and policies (or Postgres Row Level Security).
- Keep school-specific settings (grading descriptors, report headers, logo, local document requirements) configurable per school and out of the code.
- Learner transfer between adopting schools: keep the LRN as the cross-school identity, and add an explicit "transfer record" flow later.

### 8.4a Roles and who holds them
| Role | Who (first release) | Scope |
|---|---|---|
| `system_admin` | The developer | Technical settings, new schools, backups. No routine access to learner data, and any access is logged. |
| `school_admin` | The SNEd teacher (no coordinator in school) | Sections, accounts, level-change approvals, audit log, school settings |
| `sped_teacher` | The SNEd teacher | Own section: learners, IEPs, progress, attendance, health, reports |
| `regular_teacher` | Later | View-only accommodations and progress summary for mainstreamed learners |
| `parent` | Later | Own child only |

- One user can hold several roles. In a one-teacher school the same account has `sped_teacher` and `school_admin`.
- When a principal or SPED coordinator joins, add a separate `school_admin` account and remove that role from the teacher. No data changes are needed.
- Because the teacher would approve their own level changes, add an SDO or principal sign-off step on paper for now, and a second approver in the tool later.

### 8.5 Teacher (adviser) workflow
- The adviser (`sections.adviser_id`) logs in and sees **only their section's learners**: roster, IEP progress, attendance, health, documents.
- Co-teachers, aides, and therapists are added through `section_teachers`.
- Teacher dashboard:
  - Class snapshot: attendance today, learners needing attention, upcoming IEP reviews
  - Per-learner progress timeline with objective ratings and notes
  - One-tap daily logging: attendance, progress rating, behavior note
  - Missing documents per learner
- School admin/principal sees all sections and approves level changes and report packs.

### 8.6 Reports
| Report | Purpose | Notes |
|---|---|---|
| SF2 Daily Attendance (monthly) | DepEd attendance | Generated from `attendance` |
| SF1 School Register / enrollment list | Class list | Data comes from LIS, so the tool should export and match LIS and never replace it |
| SF8 Basic Health and Nutrition Report | BMI, height-for-age, per school year | Generated from `health_measurements` |
| SF9 Report Card / SF10 Permanent Record | Learner progress | For non-graded SNEd learners, confirm with the division office which format is used (narrative or descriptive). Build as configurable templates. |
| IEP summary and progress report | Parent and teacher use | Quarterly |
| Individual learner progress report | Printable for parent meetings | Charts plus teacher notes |
| Class progress summary | Teacher and principal | Counts by rating per domain |
| Health summary | School clinic/nurse | Nutritional status distribution, referrals |
| Missing documents | Enrollment follow-up | Per section |
| Level-change packet | SDO submission | Assessment form, IEP evidence, recommendation |

All reports: print-friendly PDF, plus Excel export for the ones DepEd collects in spreadsheets.

### 8.7 Compatibility note
This tool is a **school-side working tool**. LIS stays the official DepEd registry, and the tool should:
- Store LRN and enrollment status for matching
- Export data in LIS and SF-style layouts so encoding is quick
- Never be treated as the official record of enrollment

I could not confirm direct LIS integration. Assume manual or export/import until the division office says otherwise.

---

## 9. Open Questions to Confirm with the School

- Exact DepEd/LIS forms and fields the school must keep for SNED learners
- Whether the school or division has data hosting/residency rules
- Who may see which records (principal, regular teachers, therapists)
- Parent access: email, mobile number, or in-person only
- Internet reliability at school (decides whether offline mode moves up)
