# SMILE: Progress Log

The agent reads this file at the start of every session and updates it at the end. It is the project's memory. Keep it short and accurate.

## Current status

- **Current phase:** Phase 1 (Foundation)
- **Current step:** Isolated SMILE Docker setup complete; next: disable public registration
- **Last updated:** 2026-10-11

## Phase checklist

### Phase 1: Foundation
- [x] Scaffold Laravel + Inertia + React (starter kit, built-in auth, Pest)
- [x] PostgreSQL connected and base migrations run
- [ ] Public registration disabled
- [ ] Roles set up (spatie/laravel-permission) and first accounts seeded
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
- Laravel built-in authentication; public registration must be disabled next
- Pest for tests
- Teacher is the main user and also `school_admin` in the first release
- Project name: SMILE (Student Monitoring for Inclusive Learning Education)
- Learner identity: SMILE generates its own unique `smile_id`. The school's student number is not used or stored. LRN is optional.
- Separate Docker PHP and PostgreSQL services for SMILE; do not reuse SNEDY services. App: localhost:8001; database client: 127.0.0.1:5433, smile_dev, smile_app. Local password is in ignored `.env`.

## Open questions (waiting on the school or division)

- SF9 and SF10 format for non-graded learners
- LIS integration (assume manual export or import)
- Who may see which records
- Parent access method
- Data hosting rules

## Known issues and notes

- Public registration is still enabled in the starter kit. SMILE domain features and the five required authorization tests are not implemented.
- Starter tests use in-memory SQLite; PostgreSQL connection is checked separately with container Artisan commands.
- Frontend Vitest and a dedicated type-check script are not configured.
- Schema reconciliation is still needed: school_id on school-owned tables, UUID users versus starter IDs, Spatie role/audit storage, system-admin school membership, consent timing, and preservation of retired SMILE IDs.
- Existing unused smile-db container on port 5432 is left untouched; the new Compose database is smile-postgres on port 5433.

## Session history

| Date | What was done | Next step |
|---|---|---|
| 2026-10-11 | Established separate smile-php and smile-postgres services, network and volume; generated local DB password and app key; ran all three starter migrations. Backend: 27 tests passed (64 assertions); frontend build passed; login HTTP 200. Initialized Git with the owner's supplied identity. | Disable public registration as the next small Phase 1 step; resolve schema questions before domain migrations. |
