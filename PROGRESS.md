# SMILE: Progress Log

The agent reads this file at the start of every session and updates it at the end. It is the project's memory. Keep it short and accurate.

## Current status

- **Current phase:** Phase 1 (Foundation)
- **Current step:** Role foundation and UUID user IDs complete; next: dependency security updates, then school membership and first accounts
- **Last updated:** 2026-10-11

## Phase checklist

### Phase 1: Foundation
- [x] Scaffold Laravel + Inertia + React (starter kit, built-in auth, Pest)
- [x] PostgreSQL connected and base migrations run
- [x] Public registration disabled
- [x] Roles set up (spatie/laravel-permission), including teacher dual-role support
- [ ] First administrator accounts provisioned after school membership is established
- [ ] Schools table and school_id scoping
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
- schema.sql uses Laravel's name/password/email verification fields instead of full_name/password_hash to preserve built-in authentication conventions; the role enum/user_roles table is replaced by Spatie's tables in the same change as their migration.

## Open questions (waiting on the school or division)

- SF9 and SF10 format for non-graded learners
- LIS integration (assume manual export or import)
- Who may see which records
- Parent access method
- Data hosting rules

## Known issues and notes

- SMILE domain features and the five required authorization tests are not implemented. School membership and safe administrator account creation remain unfinished; there is no public account creation.
- Starter tests use in-memory SQLite; PostgreSQL connection is checked separately with container Artisan commands.
- Frontend Vitest and a dedicated type-check script are not configured.
- Schema reconciliation is still needed: school_id on school-owned tables, Spatie audit storage, system-admin school membership, consent timing, and preservation of retired SMILE IDs. User school_id, phone and is_active fields await the school foundation step.
- Composer audit reports 41 advisories across 12 existing dependency packages (including Laravel and Symfony); none target spatie/laravel-permission. Dependency security updates must be addressed before learner features. The obsolete boost:update Composer hook was removed because Laravel Boost is not installed.
- smile_testing is a separate PostgreSQL database used for backend tests; never point test commands at smile_dev.
- Existing unused smile-db container on port 5432 is left untouched; the new Compose database is smile-postgres on port 5433.
- Downloads/claude_smile contains the original planning copies. Use this repository's PROGRESS.md for actual status. SNED_Monitoring_Tool.md has an older phase breakdown; PROJECT_SPEC.md controls implementation order.

## Session history

| Date | What was done | Next step |
|---|---|---|
| 2026-10-11 | Established separate smile-php and smile-postgres services, network and volume; generated local DB password and app key; ran all three starter migrations. Backend: 27 tests passed (64 assertions); frontend build passed; login HTTP 200. Initialized Git with the owner's supplied identity. | Disable public registration as the next small Phase 1 step; resolve schema questions before domain migrations. |
| 2026-10-11 | Read the provided Markdown planning files and compared current code. Removed public registration routes, controller, page, and login/home links; retained other authentication flows. Added guest and signed-in denial tests proving no accounts are created. Backend: 29 passed (69 assertions); frontend build, Pint, and changed-file Prettier checks passed. | Roles and first-account setup; reconcile user IDs, school membership, and Spatie tables before their migrations. |
| 2026-10-11 | Installed Spatie roles; added five role definitions, dual-role tests, and no automatic role/permission grants. Aligned empty starter user/session keys to UUIDs in place with a locked guard; updated schema.sql for Laravel auth names and Spatie tables. Removed the default shared-password account seed and nonexistent Boost hook. SQLite and isolated PostgreSQL: 33 tests passed each (80 assertions); Pint and Composer validation passed. Dev migrations and role seed applied. | Address 41 existing dependency advisories, then build school membership and initial account provisioning. |
