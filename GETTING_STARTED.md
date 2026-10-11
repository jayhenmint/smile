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
APP_TIMEZONE=UTC
SCHOOL_TIMEZONE=Asia/Manila
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

For the current Docker setup, open `http://localhost:8001/login`. The local first-teacher setup command and administrator account creation are available; see [DOCKER_SETUP.md](DOCKER_SETUP.md). Public registration is disabled.

To check teacher access management, sign in as a school administrator and open **Settings → Accounts** (`http://localhost:8001/settings/accounts`). Create a fake teacher account for testing, using Generate password and copying it before submission. On that teacher's account card, toggle school-administrator access or Account is active, then choose **Save access**. Deactivation revokes the teacher's stored sessions and remembered login; reactivation allows a fresh login. Check the teacher's login in a separate browser profile so your administrator session remains open. Your own account cannot be changed through these controls; accounts with other roles are also protected. Never use real learner data for testing.

## 4. Add the project files

### Automated frontend checks

After `npm.cmd ci`, run `npm.cmd test`, `npm.cmd run typecheck` and `npm.cmd run build`. Use `npm.cmd run test:watch` while developing. Vitest 4 requires Node 20, 22 or 24+; this setup was verified with Node 24.13.0.

Put frontend tests in `resources/js/tests/`, outside `pages/` so Inertia does not bundle them as application pages. The document tests use fictional fixtures and the real Inertia form hook; workspace/head rendering and router requests are isolated. They cover submission prerequisites, upload payloads, processing/progress, error retry, reset after success, signed download links, checklist date clearing/cancel and independent attachment drafts. They do not connect to a database. Native browser file validation, actual HTTP uploads/downloads and mobile/visual review still need the walkthrough below.

### Test the current learner creation stage

1. Open `http://localhost:8001/login` and use your existing teacher account.
2. In **Settings → School years**, add the school year if needed. In **Settings → Sections**, create a section for that year and select yourself as adviser.
3. Open **Learners → Add learner**. The form displays the configured **Current school year**. Choose your section; **Enrollment school year** shows that section's year, and current-year sections are marked Current. Use fictional details, leave LRN empty with status Pending, and confirm that consent was obtained. Save.
4. The roster should show a generated `SM-` ID, the selected school year/section, and Non-graded status. Missing attachments should not block saving.
5. Test an invalid LRN and Issued without an LRN: both should fail. Add the same fictional name/birthdate without an LRN: saving should succeed with a duplicate warning.
6. A second teacher should see only learners enrolled in their own advised sections, even if they also have school-admin access.

7. In the roster, click **Manage contacts** for the fictional learner. Add a fictional name and relationship, optional phone/email/address, and mark primary/emergency contact as appropriate. Save; the contact should appear on that learner's contacts page. This does not create a parent login.
8. Check that another adviser cannot open that contacts URL, even with school-admin access. Reassign the section's adviser through Settings: the previous adviser should lose access and the newly assigned teacher should see the contacts.

9. From the roster, click **Assessments** under the fictional learner. Save a teacher assessment with a date and fictional observations/test findings. It should appear separately from disability information.
10. Add a disability category with optional fictional diagnosis date, clinic and notes. Missing medical details/files should not block saving. Another adviser must be unable to open that learner's assessments URL; neither record changes the non-graded enrollment.

11. Click the fictional learner's name in the roster to open their detail page. Select **Edit identity**, change fictional identity/address or health notes, and save. The profile should immediately show the saved values. SMILE ID, LRN details, consent and enrollment remain read-only and unchanged.
12. Another adviser (including a school admin) must be unable to open the profile/edit URL or save changes. Reassigning the adviser removes the previous teacher's access.

13. Open **Manage contacts**, click **Edit contact**, correct fictional contact details or primary/emergency flags, and save. Confirm the contact card shows the saved changes. **Cancel editing** returns to an empty Add contact form. Another adviser must not be able to update that contact URL.

14. Open **Assessments** for the fictional learner. On an existing card, choose **Edit teacher assessment** or **Edit disability information**, correct fictional details, and save. Confirm the card updates; **Cancel editing** restores that form for adding a new record. Invalid input should retain corrections and show errors. The original recorder stays unchanged, and another adviser cannot update either URL.

15. Open a fictional learner with no LRN and status Pending. In their profile, use **Record issued LRN** with a fictional 12-digit test string (including leading zeros if desired). Saving should show Issued and hide the recording form. Invalid/duplicate LRNs must fail; no existing LRN can be overwritten. SMILE ID, consent, health notes and enrollment should stay unchanged. Use real LIS-issued LRNs only when entering authorized live records.

16. Set up another school year and a section with yourself as adviser. Open the existing fictional learner profile, choose **Enroll in a school year**, select that section and an enrollment date, and save. Enrollment history should contain both years, with the same SMILE ID and unchanged learner details. Both enrollments stay non-graded. The same year cannot be added twice, even through another section; only your advised eligible sections appear. Missing LRN or attachments do not block this flow.

17. Open **Learners**, choose a fictional learner, then **Documents**. All 17 reference items initially show Missing. Use **Update checklist** to record a paper document as Submitted (with its submission date), Verified, or Expired, and add optional teacher notes. Save and reload to check persistence; returning to Missing clears its submission date and verifier. Private attachments are available after recording submission; see the next step. Optional/conditional items are not blanket requirements, and missing items do not block enrollment. Another adviser/school must not see this checklist.

18. On **Documents**, record a fictional item as Submitted with its submission date. Attach a fictional PDF, JPEG or PNG up to **2 MB**, then use **Download attachment**. The file should download with a generic SMILE filename. Links expire after five minutes; reload the checklist to obtain a new link. Another account, adviser or school must not be able to use the link. Only one attachment per item is supported; replacement/deletion and retention rules remain pending. Checklist corrections preserve existing attachments.

19. As the dual-role teacher/admin, open **Settings > Audit log** (`http://localhost:8001/settings/audit-log`). Check that fictional learner actions show their SMILE ID, recording account and time. Only your advised learners should appear, including after pagination. Another adviser/school must not see your events; reassignment removes the old adviser's history access. No private notes, LRN, file contents or edit/delete controls appear. Opening history creates its own audit event.

This stage includes initial enrollment, roster, learner detail/identity editing, guardian contacts, separate teacher/disability assessment records, annual enrollment, the document checklist, private attachments and own-advised audit history. Contact/assessment deletion, issued-LRN corrections, learner return/status workflows, attachment replacement/deletion, search/filter, exports and live Overview metrics remain pending. The learner forms follow the supplied design's card layout; mobile/browser visual review remains required. Automated backend tests run only on SQLite or the isolated `smile_testing` database, never `smile_dev`.

For section setup, open **Settings → Sections** (`http://localhost:8001/settings/sections`) as a school administrator. Add a school year first, then enter the section name, placement and optional adviser. Only active SNEd teachers from your school are eligible. New sections are non-graded; placement choices do not assign graded levels. You can edit the name/placement or reassign/clear the adviser, but the school year stays fixed. Teachers without school-admin access can view only sections they advise. Adviser changes synchronize the section_teachers adviser row; other staff assignment screens and learner access for co-teachers/aides/therapists remain pending. Test with fake sections and accounts.

For school-year setup, sign in as a school administrator and open **Settings → School years** (`http://localhost:8001/settings/school-years`). Enter the school-approved label, start date and end date, then choose whether to make it current. Selecting a current year clears the previous selection in your school. Teachers without school-admin access can view the calendar but cannot change it. School-year selection does not enroll or promote learners. Then use Settings → Sections for section/adviser setup; term dates are not generated automatically.

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
