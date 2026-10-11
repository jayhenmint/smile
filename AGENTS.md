# AGENTS.md: SMILE (Student Monitoring for Inclusive Learning Education)

The project name is **SMILE**. Use it in the app title, page titles, report headers, and README. Keep the name configurable per school where it appears on printed reports.

Instructions for AI coding agents working in this repository. Read this file first, then `PROJECT_SPEC.md`. The database design is in `schema.sql`.

## What this project is

A teacher-first web app for a SNEd (SPED) class in a Philippine public school. It tracks learner records, IEP progress, attendance, health and nutrition, and required documents, and it prints DepEd-style reports. It works alongside DepEd's LIS and is never the official enrollment record.

The main user is the SNEd teacher, who is also the school admin in this first release.

## Stack

- Laravel (PHP) with PostgreSQL
- React + TypeScript, Tailwind CSS, shadcn/ui
- Inertia.js to connect them. This is a single project, with Laravel and React in one folder (React lives in `resources/js`). The owner has chosen this. Do not split it into a separate API and SPA.
- Authentication: Laravel's built-in authentication from the React starter kit. No WorkOS or other third-party auth. **Disable public registration.** Users are created only by a `school_admin` (or by the `system_admin` for the first accounts). Keep login, logout, password reset, and email verification.
- `spatie/laravel-permission` for roles, `spatie/laravel-activitylog` for audit
- Pest for backend tests, Vitest for frontend tests

## Source of truth

- `PROJECT_SPEC.md`: what to build, roles, rules, build order, acceptance checks
- `schema.sql`: the intended data model. Convert it to Laravel migrations faithfully. If you must change it, change the migration and update `schema.sql` in the same commit and say why.
- `docs/IEP_REFERENCE.md`: review of the owner-supplied local `IEP SAMPLE.docx`, the basis for Phase 2 IEP structure and print workflow. Read it before IEP work; resolve its form questions with the owner. The sample contains filled-in sensitive details and stays out of Git until a blank/anonymized template is available. Phase order remains unchanged.

## Session start and end (you have no memory between sessions)

Before learner grouping, reporting-period, SF9, or transition-plan work, also read `docs/CURRICULUM_REPORT_REFERENCE.md` for owner-confirmed requirements and unresolved source-form questions. It does not change phase order.

At the **start** of every session:
1. Read `AGENTS.md`, `PROJECT_SPEC.md`, and `PROGRESS.md`.
2. Check the real state of the project: `git log --oneline -10`, `git status`, and run the tests.
3. If `PROGRESS.md` disagrees with the code, trust the code and tell the owner.
4. Say in two or three lines where the project stands and what you propose to do next. Then wait for the owner to confirm.

At the **end** of every session, or after each finished step:
1. Run the tests.
2. Update `PROGRESS.md`: tick finished items, set the current step, add a row to the session history, and note unfinished work, bugs, or decisions.
3. Commit the code and `PROGRESS.md` together.

Never rely on remembering a previous conversation. Anything that matters goes in `PROGRESS.md`, `PROJECT_SPEC.md`, or the code.

## How to work

1. **Build in phase order** from `PROJECT_SPEC.md`. Do Phase 1, then 2, then 3. Do not start a later phase early.
2. **Work in small steps.** One feature at a time: migration, model, policy, controller, UI, tests. Stop and report after each step.
3. **Ask before guessing** on anything in the spec's "Open questions" section, or any DepEd form layout, rule, or term. Do not invent form layouts, field names, or policy details. Write the question down and use a configurable placeholder.
4. **Do not add features** that are not in the current phase.
5. Prefer plain, readable code and the framework's conventions over clever abstractions.
6. Commit each tested completion stage locally with its PROGRESS.md update. Before every push to GitHub, report the completed stage, checks, and any unfinished work, then wait for the owner's explicit approval to push. Permission to continue development is not permission to push.

## Hard rules (domain)

- **SMILE ID** (`learners.smile_id`) is the tool's own learner ID. **Generate it in the app when a learner is created.** It is never typed in, never edited, never reused, and never taken from the school's student number. SMILE does not store the school's student ID at all. Format `SM-` plus 8 random characters from `A-Z` (without I and O) and `2-9`. Build it from a cryptographically secure random source, not from name, birthdate, LRN, or a counter. Retry on a unique-constraint collision. Show it on every printed report.
- **LRN** is a 12-digit string issued by DepEd's LIS, and it is **optional**. Never generate it. Validate the format when present. Allow empty (`lrn_status` `pending`). Store as text, never a number (leading zeros). `lrn_status = 'issued'` requires an LRN. A learner without an LRN is identified by the SMILE ID.
- When creating a learner without an LRN, **warn (do not block)** if the same name and birthdate already exist in the school.
- **SNEd learners are non-graded.** New enrollments default to `non_graded` and are re-enrolled each school year. Only an approved `learner_level_changes` record may move a learner to a graded level. Never auto-promote.
- **The tool never decides readiness** for mainstreaming or a level change. It records evidence, notes, and decisions made by people.
- **BMI** is calculated. Nutritional status and height-for-age categories come from WHO growth standards by age and sex. Use a tested lookup, never ad hoc thresholds. Label results as screening, not diagnosis.
- **Every school-owned table has `school_id`.** Scope every query by it with a global scope.

## Hard rules (security and privacy)

This system holds medical and disability records of minors under the Data Privacy Act (RA 10173).

- Authorize every controller action with a Policy. No unauthenticated or unscoped access to learner data, ever.
- Advisers see only learners in their own section(s). Parents see only their own child. `system_admin` has no routine access to learner data.
- Write an audit entry for viewing, creating, editing, deleting, and exporting learner records.
- Encrypt health, diagnosis, and medication fields with Laravel's `encrypted` casts.
- Files (documents, evidence photos) go to private storage and are served through signed URLs only.
- Never commit real learner data, `.env`, keys, or database dumps. Use factories and fake data for seeds and tests.
- Never log learner names, LRNs, or health data in application logs or error messages.
- Do not send learner data to third-party services without asking the owner.

## Roles

`system_admin`, `school_admin`, `sped_teacher`, `regular_teacher`, `parent` (and an optional read-only `principal`). A user may hold several roles. In the first release the teacher holds both `sped_teacher` and `school_admin`. The adviser is a teacher assigned to a section through `sections.adviser_id`. It is not a separate role.

## UI guidelines

- Teacher-first. The daily screens come first: today's attendance, quick progress logging, "needs attention" list, learner timeline.
- Fast, few clicks, large touch targets. Many teachers will use a phone or an older laptop.
- Print-friendly report views (A4 and Letter). Test PDF output.
- Accessible: labels on every input, keyboard navigation, sufficient contrast.
- English first. Keep text in translation files so Filipino can be added later.

## Testing requirements

Do not call a task done unless tests pass. These authorization tests must exist and stay green:

1. An adviser cannot read or edit a learner in another adviser's section.
2. A user from school A cannot read any data from school B.
3. A parent can read only their own child's records.
4. `system_admin` cannot open learner records without an audit entry.
5. A non-graded learner cannot become graded without an approved level change.

Also add tests for: SMILE ID generation (format, global uniqueness, collision retry, cannot be changed after creation, not equal to or derived from the LRN), LRN validation (optional, 12 digits when present, `issued` requires an LRN), the duplicate warning for learners without an LRN, BMI calculation, and each report's totals.

## Commands

Fill these in once the project is scaffolded, and keep them current.

```
# start:       docker compose up -d --build
# install:     docker compose exec php composer install  and  npm.cmd install
# dev server:  http://localhost:8001 (PHP container); npm.cmd run dev for Vite
# migrate:     docker compose exec php php artisan migrate
# backend tests: docker compose exec php php artisan test
# frontend tests: not configured yet (Vitest required)
# lint/format: docker compose exec php ./vendor/bin/pint  and  npm.cmd run lint
# type check:  npx.cmd tsc --noEmit (dedicated script not configured yet)
```

## Definition of done (per task)

- Works as described in `PROJECT_SPEC.md` for the current phase
- Migration, model, policy, and tests included
- Authorization and school scoping verified
- Audit logging in place where learner data is touched
- No real data, secrets, or personal information in code or logs
- A short note on what changed and what to check next

## When unsure

Stop and ask the owner. A wrong guess about DepEd rules or learner privacy costs more than a short question.
