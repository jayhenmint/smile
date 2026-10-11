# SMILE: Getting Started

The current local setup uses separate SMILE Docker services. Follow [DOCKER_SETUP.md](DOCKER_SETUP.md) for the working commands and database-client connection. The walkthrough below describes the original scaffold setup. Public registration has now been disabled.

Follow these steps in order. Commands are from general knowledge of Laravel's tooling. Versions change, so if a command fails, check the official docs (laravel.com/docs, "Installation" and "Starter Kits") before debugging.

## 1. Install the prerequisites

| Tool | Version | Check with |
|---|---|---|
| PHP | 8.2 or newer | `php -v` |
| Composer | latest | `composer -V` |
| Node.js | 20 or newer | `node -v` |
| PostgreSQL | 15 or newer | `psql --version` |
| Git | any | `git --version` |

Easy options: **Laravel Herd** (Windows/macOS) installs PHP and Composer. **DBngin** or the PostgreSQL installer gives you the database. Or use Docker if you prefer.

## 2. Create the project

```bash
composer global require laravel/installer
laravel new smile
```

When the installer asks:
- **Starter kit:** React (this gives you Inertia, TypeScript, Tailwind, and shadcn/ui). This is one project, with Laravel and React together.
- **Authentication provider:** Laravel's built-in authentication (not WorkOS, not none)
- **Database:** PostgreSQL
- **Testing framework:** Pest
- Run `npm install` and `npm run build` when offered

```bash
cd smile
```

## 3. Create the database

```bash
psql -U postgres
CREATE DATABASE smile_dev;
\q
```

Open `.env` and set:

```
APP_NAME=SMILE
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smile_dev
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Then run:

```bash
php artisan migrate
php artisan serve
npm run dev
```

For the current Docker setup, open `http://localhost:8001/login`. Accounts must be created by an administrator; first-account setup is still pending.

## 4. Add the project files

Copy these into the project root (the `smile/` folder):
- `AGENTS.md`
- `PROJECT_SPEC.md`
- `schema.sql`

Keep `.env` out of Git (Laravel's `.gitignore` already does this). Never put real learner data in the repo.

## 5. Install the packages from the spec

```bash
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
npm install recharts react-hook-form zod
```

Publish their config and migrations as each package's docs describe.

## 6. Make the first commit

```bash
git init
git add .
git commit -m "Scaffold SMILE with Laravel, Inertia, React"
```

Commit after every finished step so you can undo a bad change.

## 7. Start your AI agent

Open the `smile/` folder in your agent (Claude Code or similar) and paste the prompts below **one at a time**. Review the result and run the tests before the next one.

### Prompt 1: Orientation
```
Read AGENTS.md and PROJECT_SPEC.md. Then summarize in your own words:
the roles, the non-graded learner rule, the LRN rule, and the five
authorization tests. Do not write code yet. List any questions.
```

### Prompt 2: Roles and school scoping
```
Phase 1, step 1. Set up spatie/laravel-permission with the roles in
PROJECT_SPEC.md (system_admin, school_admin, sped_teacher, regular_teacher,
parent). Disable public registration: users are created only by a
school_admin (or by the system_admin for the first account), and keep login,
logout, password reset, and email verification. Add a seeder or artisan
command that creates the first system_admin and one school_admin plus
sped_teacher account (the same person for the first release).
Create the schools table and add school_id to users. Add a global
scope that limits queries by the user's school_id. Seed roles only (no
learner data). Write Pest tests for the school scoping. Stop and report.
```

### Prompt 3: School structure
```
Phase 1, step 2. From schema.sql create migrations and models for
school_years, sections, and section_teachers. Sections default to
grade_level 'Non-graded'. Add a policy so an adviser sees only their own
sections. Write tests. Stop and report.
```

### Prompt 4: Learner profile
```
Phase 1, step 3. Create migrations, models, and a policy for learners,
guardians, learner_guardians, learner_disabilities, and enrollments from
schema.sql. Every learner gets a smile_id that the app generates on
creation (format SM-plus 8 random characters, see AGENTS.md). It is never
typed in, never changed afterwards, and the school's student number is not
stored at all. LRN is optional: a 12-digit string when present, nullable
while lrn_status is pending, and lrn_status 'issued' requires an LRN. Warn
(do not block) when a learner without an LRN has the same name and
birthdate as one already in the school. Enrollments default to
non_graded. Encrypt health_notes. Add form requests with validation and
tests for SMILE ID generation and immutability, LRN validation, the
duplicate warning, and adviser access. Stop and report.
```

### Prompt 5: Roster and learner screens
```
Phase 1, step 4. Build the Inertia and React screens: learner list for the
adviser's section, add/edit learner form, and a learner detail page. Use
shadcn/ui. Keep it fast, simple, and mobile-friendly. Add tests. Stop and
report.
```

### Prompt 6: Documents and audit
```
Phase 1, step 5. Add document_types and learner_documents with a per-learner
checklist (required, submitted, verified, missing) and private file upload
served with signed URLs. Add activity logging for viewing, editing, and
exporting learner records. Write the five authorization tests from
PROJECT_SPEC.md section 9 as far as they apply to Phase 1. Stop and report.
```

When Phase 1 is done, you can create learners, see the roster, and see missing documents. Then move to Phase 2 (IEP and progress).

## 8. Review checklist after every step

- [ ] `php artisan test` passes
- [ ] No real names, LRNs, or health data in code, seeds, tests, or logs
- [ ] A different adviser and a different school cannot see the data
- [ ] You understand what the agent changed (read the diff)
- [ ] Committed to Git

## 9. Before using real learner data

- Ask the school head and division office the open questions in `PROJECT_SPEC.md` section 10
- Get parental consent forms ready (data privacy, enrollment)
- Choose a host that meets any school or division rules
- Turn on backups and HTTPS
- Test with fake learners with the teacher first

## 10. If you get stuck

Paste the exact error message and the command you ran, and ask for help. Do not paste real learner data.
