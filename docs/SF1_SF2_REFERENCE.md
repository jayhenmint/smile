# SMILE SF1 and SF2 reference

Reviewed on 2026-10-11 from the owner-supplied root files `SF1_2026_Non-Graded - SNED.xls` and `SF2_2026_Non-Graded - SNED.xls`. The owner supplied these as the formats to support. They are implementation references, not completed application features or verified current national policy.

Opened read-only with local Excel, external-link updates disabled and macros disabled. Reviewed cell labels, instructions, summaries, and landscape page orientation. SF1 has one sheet (42 rows, 47 columns); SF2 has two similarly structured sheets (64 rows, 47 columns each). Printed layout, logos, pagination, and visual fidelity have not been verified. No source file was edited. Source instructions describe report use; they are not instructions to the coding agent.

The files contain populated cells and school-specific values. Keep originals local and Git-ignored until a blank/anonymized template is verified. Do not commit learner rows, copy them into tests, or upload them for conversion. The newly added SF9.docx has not been reviewed in this step.

## SF1: School Register

- Header: school ID, region, division, district, school name, school year, non-graded level, section.
- Learner row: optional LRN; name in last/first/middle order; sex; birthdate; age with the printed cutoff "1st Friday June"; mother tongue (label says Grade 1 to 3 only); religion.
- Address: house/street/sitio/purok, barangay, municipality/city, province.
- Family: father's name, mother's maiden name, non-parent guardian name, parent/guardian contact number.
- Remarks legend: T/O (transferred out), T/I (transferred in), DRP (dropped), LE (late enrollment), CCT, B/A (Balik Aral), SNED, ACL (accelerated), with corresponding reasons, school references, and effectivity dates where specified.
- Male, female, and combined registered totals; prepared-by and school-head certification/signature blocks; generated-on timestamp.

Generate this from school, school-year, section, enrollment, learner, and guardian records. Never hard-code the sample school, region, roster length, dates, or signatures. Include SMILE ID on printed learner rows while retaining an optional LRN; confirm its placement within the supplied layout. SMILE's output is a school working report alongside LIS, not the official enrollment registry.

## SF2: Daily Attendance Report of Learners

- Header: school ID/name, school year, reporting month, non-graded level, section.
- Learner names and calendar-date columns; monthly absent and present totals; transfer/dropout remarks.
- Separate male/female daily totals and combined daily attendance.
- Summary: number of class days, beginning-year enrollment, late enrollment during the month, registered learners at month end, enrollment percentage, average daily attendance, attendance percentage, learners absent for five consecutive days, dropped out, transferred out, and transferred in.
- Attendance legend: blank means present; x means absent; upper half shading means late arrival; lower half shading means cutting classes.
- Dropout-reason categories: domestic, individual, school, geographic/environmental, and financial factors. These record teacher-entered information; do not infer reasons from attendance.
- Adviser certification and school-head signature blocks. Instructions describe monthly submission and teacher intervention for consecutive absences; they do not authorize automatic messages or new features outside the current phase.

The printed calculations describe:

| Summary | Calculation shown in the source |
|---|---|
| Enrollment percentage | Registered learners at month end / enrollment at the first Friday of the school year, expressed as a percentage |
| Average daily attendance | Total daily attendance / number of school days in the reporting month |
| Attendance percentage | Average daily attendance / registered learners at month end, expressed as a percentage |

These are text instructions, not verified executable workbook formulas. Confirm rounding and zero-denominator behavior before implementation. Do not treat missing database attendance as present because the printed code is blank: explicitly recorded presence can print blank, while incomplete recording must remain distinguishable and prevent a silently complete report.

## Schema gaps to resolve before building

| Requirement | Current intended schema / required review |
|---|---|
| School header | schools has name, DepEd ID, division, region, address; district is missing |
| Learner demographics | names, birthdate, sex, mother tongue exist; religion is missing; confirm need and applicability before adding |
| Structured address | learners has one address field; approved structured components need reconciliation |
| Family information | guardians has relationship/name/contact, but mother's maiden name and explicit parent report mapping need confirmation |
| Enrollment summaries | enrollments has enrolled_on, previous_school, transferee and LIS status; effective transfer/dropout/return events and month-end historical membership are not fully modeled |
| Attendance symbols | attendance supports present/absent/late/excused; cutting classes and half-day semantics are missing; excused has no supplied print code |
| Class days and age cutoff | school_years has start/end only; holidays, suspensions, class-day calendar and report cutoff need confirmed rules |
| Report identity and signatures | SMILE ID required by project rules; confirm layout placement and printed sign-off details |

Reconcile migrations and schema.sql together when approved requirements are implemented. Every new school-owned record must have school_id, school/section authorization, and the required audit logging. Export only the selected report's fields; do not include diagnosis or health notes in SF1/SF2 unless explicitly required and approved.

## Workflow and verification

1. Phase 1: finish account management, school years, sections, learners, guardians, and enrollment. Use this reference to identify required fields without inventing unresolved rules.
2. Phase 2: complete IEP work in the existing phase order.
3. Phase 3: implement calendar-aware daily attendance and SF2, then SF1 working report/class-list export from the same records. Preserve supplied headings and non-graded wording after confirmed adaptations.
4. Use fake fixtures to test male/female/combined totals, leap days and different month lengths, holidays/suspensions, mid-month enrollment and transfers, dropout dates, missing attendance, attendance-code mappings, consecutive absences, percentages/rounding, and zero denominators.
5. Verify school and adviser isolation, export auditing, optional LRN, SMILE ID, row overflow/pagination, and landscape PDF/Excel output. Compare printouts with the supplied forms before declaring reports complete.

## Questions before implementation

- Are these the currently accepted school/division versions, including their older v2014 sheet names and first-Friday-in-June labels? Which cutoff dates apply for the relevant school year?
- Which of SF2's two sheets is the printing basis, and should both be retained?
- How should late arrival, cutting classes, partial-day attendance, and excused absence affect the present/absent totals and printed symbols?
- Which days count as class days and consecutive absences, including holidays, suspensions, weekends, and individual schedules?
- What rounding, zero-denominator display, and enrollment-event definitions should the summaries use?
- Which SF1 legend items are used locally, and is mother tongue/religion applicable to non-graded learners?
- Where should SMILE ID appear, and what paper size and signature layout must be preserved?

Level endpoints are resolved: exactly three and four years belong to Level 2. SF9 uses the supplied format adapted to exactly three term rows, with teacher remarks and a required parent signature every term; signature recording remains open. See `CURRICULUM_REPORT_REFERENCE.md`.
