# SMILE: Progress Log

The agent reads this file at the start of every session and updates it at the end. It is the project's memory. Keep it short and accurate.

## Current status

- **Current phase:** Phase 1 (Foundation)
- **Current step:** Source review and owner-confirmed groupings, terms, SF9 basis, and separate IEP/ITP are documented; app remains in Phase 1. Account creation, password generation, and reference documentation await push approval. Next development: existing account role and activation management, then school years and sections.
- **Last updated:** 2026-10-11

## Phase checklist

### Phase 1: Foundation
- [x] Scaffold Laravel + Inertia + React (starter kit, built-in auth, Pest)
- [x] PostgreSQL connected and base migrations run
- [x] Public registration disabled
- [x] Roles set up (spatie/laravel-permission), including teacher dual-role support
- [x] Composer dependency security update (audit clear on 2026-10-11)
- [x] First local teacher account provisioned with school_admin and sped_teacher roles
- [x] School settings screen
- [x] School account list and teacher account creation
- [ ] Existing account role and activation management
- [x] Schools table and school_id scoping
- [ ] School years, sections, section_teachers
- [ ] Learners, guardians, disabilities, enrollments (generated SMILE ID, optional LRN rules)
- [ ] Roster and learner screens
- [ ] Documents checklist and private file upload
- [ ] Audit logging
- [ ] Phase 1 authorization tests passing

### Phase 2: Core value
- [ ] IEP builder
- [ ] Progress logging
- [ ] Progress timeline and trend view
- [ ] IEP review dates and "needs attention" list

### Phase 3: Daily management and reports
- [ ] Attendance and SF2 report
- [ ] Behavior log
- [ ] Health and nutrition and SF8 report
- [ ] Printable reports and Excel export

### Phase 4 to 6
Not started. See `PROJECT_SPEC.md`.

## Decisions made

- Single project: Laravel + Inertia + React (not a separate API and SPA)
- Laravel built-in authentication; public registration disabled. Keep login, logout, password reset, and email verification.
- Pest for tests
- Teacher is the main user and also `school_admin` in the first release
- Project name: SMILE (Student Monitoring for Inclusive Learning Education)
- Learner identity: SMILE generates its own unique `smile_id`. The school's student number is not used or stored. LRN is optional.
- Separate Docker PHP and PostgreSQL services for SMILE; do not reuse SNEDY services. App: localhost:8001; database client: 127.0.0.1:5433, smile_dev, smile_app. Local password is in ignored `.env`.
- User and session user IDs use UUIDs as specified. Existing empty PostgreSQL starter columns are aligned in place under a lock; the migration refuses databases containing accounts or authenticated sessions.
- Role definitions use Spatie's standard tables and UUID model keys. They are global framework metadata; school and section access must still be enforced through scopes and policies.
- Default seeding creates only the five required roles, with no permissions or default accounts. Optional principal is not seeded until requested. No system-admin permission bypass is installed.
- Each tested completion stage is committed locally. Every GitHub push requires the owner's explicit approval after review of the stage and checks; permission to continue development does not authorize a push.
- schema.sql uses Laravel's name/password/email verification fields instead of full_name/password_hash to preserve built-in authentication conventions; the role enum/user_roles table is replaced by Spatie's tables in the same change as their migration.
- School and user queries are globally scoped to the signed-in user's school; guests, inactive users, and accounts without a school receive no school-owned records. System administrators can access school metadata but receive no school-owned records, even when also assigned a teacher role.
- Authentication, remember-token, and password-broker account lookups bypass only the school scope so login can establish a school context; these lookups require an active account. School membership and activation cannot be mass-assigned.
- User school_id may be null for global system administrators or parents as the schema permits. Parent access to linked learners remains to be implemented separately; no learner records exist yet.
- The developer can bootstrap the first local teacher with smile:create-local-teacher. It requires APP_ENV=local and an empty users table, creates a development school, generates a cryptographic password, and grants only school_admin and sped_teacher. It does not verify email automatically or reset existing accounts. Production provisioning remains separate work.
- School settings at /settings/school edit only the authenticated user's school, authorized by SchoolPolicy::update. Request IDs never select a school. The existing schema's name, DepEd school ID, division, region, and address are editable; DepEd school ID is optional, a six-digit string, and globally unique. New UI text is in lang/en/school_settings.php.
- Accounts at /settings/accounts are listed and created only by an active school_admin with a school membership and no system_admin role. Lists are globally school-scoped and paginated. New accounts always have sped_teacher, with optional school_admin; parent and regular-teacher creation belongs to later phases. Account creation is transactional, uses a hashed initial password, and leaves email verification pending. It rejects arbitrary roles, permissions, school IDs, activation, and verification attributes. No email or third-party invitation is sent.
- Teacher account creation has a Generate password option using browser cryptographic randomness (16 random bytes, displayed as 32 hexadecimal characters). It fills both password fields and shows them for copying; manual entry and Show/Hide remain available. Password values clear after submission and are never stored in the account list or browser storage by this feature.
- The owner-supplied local IEP SAMPLE.docx is the basis for Phase 2 IEP sections, six domain labels, and printing. Read docs/IEP_REFERENCE.md before IEP work. The original contains filled-in sensitive details, is Git-ignored, and remains unchanged; commit only a blank/anonymized template. Form questions and schema gaps require confirmation before implementation; Phase 1 remains the current phase.

- Confirmed on 2026-10-11: Level 1 = 2–3 years studied in the school, Level 2 = 3–4 years, Level 3 = 4–6 years (corrected from the text file); Transition Program = ages 15–21. These are school groupings, not graded levels, and never trigger automatic promotion.
- Confirmed: report-card columns 1–3 are Terms 1–3; the supplied Annex is the school's approved SF9 basis. DepEd's May 4, 2026 clarification supports three terms from SY 2026–2027. Preserve school-year-specific historical periods.
- Confirmed: learner-specific IEP and ITP are separate. Read docs/CURRICULUM_REPORT_REFERENCE.md before grouping, reporting, or transition work; later-phase features remain unimplemented.

## Open questions (waiting on the school or division)

- Overlapping levels at three/four years, year-counting rules, other duration/age boundaries, and relationship between level and Transition Program.
- SF9 adaptation of its legacy four-quarter remarks/signature rows, With/Without assessment field, NO/NA handling, and attendance calendar; SF10 format remains unconfirmed.
- ITP fields, review schedule, approvals, and signatures.
- LIS integration (assume manual export or import)
- Who may see which records
- Parent access method
- Data hosting rules
- IEP form approval, review-date label, approval/amendment statuses, domains/objectives, services/schedule, and signatures (see docs/IEP_REFERENCE.md).

## Known issues and notes

- Learner features and the five required learner authorization tests are not implemented. School settings, scoped account listing and teacher creation, and local first-account provisioning exist. Existing account role/activation management, production provisioning, and global school onboarding remain unfinished. There is no public account creation.
- Backend tests pass using both in-memory SQLite and the separate PostgreSQL test database.
- Frontend Vitest and a dedicated type-check script are not configured.
- Browser automation is unavailable in the current tool session. School settings and Accounts passed frontend build/type/lint checks and authenticated HTTP checks; visual and mobile interaction review remains for the owner.
- Schema reconciliation is still needed: school_id on remaining school-owned tables, Spatie audit storage, consent timing, and preservation of retired SMILE IDs. User school_id, phone, and is_active fields are now implemented; school deletion is restricted while users reference it.
- Composer audit --locked reports no security advisories after the 2026-10-11 update. Updated 57 packages within existing constraints, including Laravel 12.55.1 to 12.69.3 and Symfony 7.4 components. No major framework migration or application/schema changes were required. The obsolete boost:update Composer hook was removed earlier because Laravel Boost is not installed.
- smile_testing is a separate PostgreSQL database used for backend tests; never point test commands at smile_dev.
- Existing unused smile-db container on port 5432 is left untouched; the new Compose database is smile-postgres on port 5433.
- Downloads/claude_smile contains the original planning copies. Use this repository's PROGRESS.md for actual status. SNED_Monitoring_Tool.md has an older phase breakdown; PROJECT_SPEC.md controls implementation order.

## Session history

| Date | What was done | Next step |
|---|---|---|
| 2026-10-11 | Recorded the owner's corrected school-duration levels, age-based Transition Program, Terms 1–3, approved Annex SF9 basis, and separate learner-specific IEP/ITP. Added docs/CURRICULUM_REPORT_REFERENCE.md with source summaries, phase mapping, separate assessment scales, and unresolved boundaries/form adaptations. Verified DepEd's three-term calendar clarification for SY 2026–2027. Application and schema unchanged. Backend: 72 tests passed (409 assertions); diff whitespace checks passed. The newly added SF1 spreadsheet was left untouched and excluded from this documentation commit. | Confirm overlapping level boundaries and the Annex's legacy quarter-row adaptation. Continue Phase 1; explicit approval required before pushing. |
| 2026-10-11 | Established separate smile-php and smile-postgres services, network and volume; generated local DB password and app key; ran all three starter migrations. Backend: 27 tests passed (64 assertions); frontend build passed; login HTTP 200. Initialized Git with the owner's supplied identity. | Disable public registration as the next small Phase 1 step; resolve schema questions before domain migrations. |
| 2026-10-11 | Read the provided Markdown planning files and compared current code. Removed public registration routes, controller, page, and login/home links; retained other authentication flows. Added guest and signed-in denial tests proving no accounts are created. Backend: 29 passed (69 assertions); frontend build, Pint, and changed-file Prettier checks passed. | Roles and first-account setup; reconcile user IDs, school membership, and Spatie tables before their migrations. |
| 2026-10-11 | Installed Spatie roles; added five role definitions, dual-role tests, and no automatic role/permission grants. Aligned empty starter user/session keys to UUIDs in place with a locked guard; updated schema.sql for Laravel auth names and Spatie tables. Removed the default shared-password account seed and nonexistent Boost hook. SQLite and isolated PostgreSQL: 33 tests passed each (80 assertions); Pint and Composer validation passed. Dev migrations and role seed applied. | Address 41 existing dependency advisories, then build school membership and initial account provisioning. |
| 2026-10-11 | Updated the 12 advisory-affected Composer packages and required dependencies (57 version updates). Audit now has zero advisories; Composer validation and platform requirements passed. SQLite and isolated PostgreSQL: 33 tests passed each (80 assertions). Frontend build passed, login HTTP 200, and dev migration status verified. Recorded mandatory owner approval before every push in AGENTS.md. Stage committed locally; push awaits approval. | Obtain push approval; then build schools and school_id scoping before initial administrator accounts. |
| 2026-10-11 | Dependency update b91ffbd was pushed with owner approval. Added schools, user membership fields, school metadata policy, global school scopes, and active-only authentication lookup. Added isolation, policy, inactive-account, login, and protected membership tests. SQLite and isolated PostgreSQL: 43 tests passed each (130 assertions); Pint, TypeScript, and frontend build passed. Fixed starter form typings and expressed the existing blend mode through Tailwind to pass TypeScript; corrected frontend UUID user ID type. Dev migration applied and login HTTP 200. | Obtain approval before pushing this school foundation stage; then provision first administrators and add school setup screens. |
| 2026-10-11 | Pushed school foundation 97614e0 with explicit approval. Added local-only first-teacher command with input validation, generated password, dual roles, and refusal to change existing accounts. Provisioned a fake development school and local teacher; real HTTP login reached dashboard with status 200. Credentials shared privately in the conversation, never committed. SQLite and PostgreSQL: 46 tests passed each (152 assertions); Pint passed. | Owner tests login; obtain approval before pushing the new account-setup commit. Next: school setup screens and administrator account management. |
| 2026-10-11 | Account setup 2c55662 was pushed with owner approval. Added own-school settings page and navigation, policy-authorized read/update actions, schema-backed field validation, and English translation text. Added authorization, request ID tampering, optional fields, leading-zero IDs, and global uniqueness tests. SQLite and isolated PostgreSQL: 55 tests passed each (255 assertions). Pint, changed-file ESLint/Prettier, TypeScript, and frontend build passed. Existing local login opened /settings/school with HTTP 200. Backend school tests now run independently of the Vite manifest; visual browser review remains pending. | Owner tests School settings; obtain approval before pushing this stage. Next: administrator account management. |
| 2026-10-11 | School settings d33eb74 was pushed with owner approval. Added Accounts page with scoped pagination, UserPolicy, transactional teacher creation, optional school_admin role, and translated UI text. Rejected role/permission/school/activation/verification injection and tested duplicate emails, input validation, credential hashing, and login with new accounts. SQLite and isolated PostgreSQL: 72 tests passed each (409 assertions); Pint, changed-file ESLint/Prettier, TypeScript, and frontend build passed. Existing local teacher opened Accounts with HTTP 200; no extra dev accounts were created by verification. | Owner tests Accounts; obtain approval before pushing this stage. Next: existing account role and activation management, then school years and sections. |
| 2026-10-11 | Added Generate password and Show/Hide options to teacher account creation. Browser cryptographic randomness fills both password fields; generated passwords are visible for copying and clear after submission. Updated translated help and setup instructions. SQLite and isolated PostgreSQL: 72 tests passed each (409 assertions); TypeScript, changed-file ESLint/Prettier, Pint, and frontend build passed. Visual interaction review remains pending. | Owner tests the password option; obtain approval before pushing account creation dd8abf0 and this follow-up. |
| 2026-10-11 | Reviewed the owner-supplied local IEP SAMPLE.docx text, tables, and header/footer against the spec and schema. Added docs/IEP_REFERENCE.md with the section/domain basis, proposed improvements, schema gaps, confirmation questions, and Phase 2 workflow. Linked it from AGENTS.md and PROJECT_SPEC.md. Kept the original unchanged and Git-ignored because it contains filled-in sensitive details; no learner examples were copied into documentation. Visual print/image review was not performed. Backend: 72 tests passed (409 assertions); diff whitespace checks passed. | Confirm form status and outstanding IEP questions before Phase 2. Continue Phase 1 in order; obtain approval before any push. |
