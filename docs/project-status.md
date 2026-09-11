# Project Status

Last updated: 2026-09-11

## Current State

School CBT is a standalone Laravel 13, Vue 3, TypeScript, Inertia, and MySQL/MariaDB application for local school CBT delivery over a LAN.

The repository has a secure CBT kernel, deployment-readiness commands, pilot setup tooling, roster import, staff question-bank management, and exam draft authoring.

## Completed Foundations

- Fresh standalone Laravel app created in `school_cbt`.
- Breeze Vue/TypeScript/Inertia authentication installed.
- Laravel Boost installed and committed.
- MySQL/MariaDB defaults configured in `.env.example`.
- Core schema added for identity, academic catalog, question bank, exams, attempts, grading, audit, and backups.
- Roles and permissions registry added.
- `school:setup` command added for pilot setup.
- `roster:import` command added for CSV student import.
- `backup:create` and `backup:restore` commands added.
- `health:check` command and `/health` JSON endpoint added.

## Completed CBT Kernel

- Immutable exam revision publishing.
- Attempt-specific question snapshots.
- Stable question and option ordering for attempts.
- Timed attempts with server-side expiry.
- Autosave with client sequence protection.
- Idempotent submission flow.
- Objective grading.
- Manual grading support for essay/manual questions.
- Result release filtering.
- Student attempt-taking page.
- Staff invigilation dashboard.

## Completed Pilot Data Flow

- `school:setup` seeds roles and permissions.
- Creates current academic session and three terms.
- Creates JSS 1 to SS 3 class levels.
- Creates A, B, and C arms for each class.
- Seeds common secondary-school subjects.
- Seeds default question categories for each subject/class pair.
- Creates starter admin, exam officer, question author, invigilator, and grader accounts.
- Can generate a sample student roster CSV.
- `roster:import` creates or updates students, optional login users, and enrollments.

## Completed Question Bank Work

- Staff question list page.
- Staff question create/edit page.
- Question creation with subject, class level, category, tags, type, difficulty, marks, options, answer fractions, explanation, and feedback.
- Mark question as ready.
- Retire question.
- Draft versions update in place.
- Ready versions are not mutated; edits create a new draft version.
- Staff edit payload can include answer keys because it is permission-protected.
- Student attempt payloads remain answer-key safe.

## Completed Exam Draft Work

- Staff exam draft list page.
- Staff exam create/edit page.
- Exam settings for subject, term, type, duration, marks, attempts, timing, shuffle, and result-release controls.
- Fixed ready-question draft slots.
- Draft slots reject draft, retired, not-ready, and wrong-subject question versions.
- Submit, approve, and publish lifecycle actions.
- Approved drafts publish immutable exam revisions through the existing publisher.

## Verification

Latest successful checks after exam draft work:

- `vendor/bin/pint --dirty --format agent`
- `php artisan test --compact`: 69 tests passed
- `npm run build`

## Git State

Last pushed commit:

- `d0b457b Add Boost and deployment readiness tools`

Current uncommitted work:

- exam draft authoring controller/routes/pages/tests
- staff question-bank controller/routes/pages/tests
- expanded pilot setup and documentation updates

## Next Recommended Feature

Build exam scheduling and audience assignment.

The reason is practical: the app can now set up a school, import students, create ready questions, assemble exam drafts, approve them, and publish immutable revisions. The next missing workflow is deciding which students can see and start each published exam.

Suggested scope:

- exam audience UI for class levels and sections
- per-student accommodations for extra time, alternate windows, and extra attempts
- student exam list filtered by enrollment and schedule
- attempt-start checks for audience membership and open/close windows
- tests for audience filtering, schedule gates, and accommodation overrides
