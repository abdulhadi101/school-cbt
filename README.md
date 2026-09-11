# School CBT

School CBT is a standalone computer-based testing application for local school CA tests and exams.

The project is being extracted from the existing SMS CBT module, but it will be rebuilt as an independent single-school application rather than a direct copy of the SMS.

## Target Stack

- Laravel/PHP backend
- Vue 3 and TypeScript frontend
- MySQL/MariaDB database
- Local file storage
- Browser-based LAN access
- FrankenPHP candidate, with Nginx/PHP-FPM fallback

## Pilot Target

- 120 concurrent students
- Local school server
- 256 GB SSD
- 16 GB RAM recommended
- External/cloud backup
- No internet dependency for core exams

## Version One Focus

- Local users, students, staff, classes, sections, subjects, sessions, and terms
- Independent question bank
- Staff question creation and review workflow
- Immutable exam revisions
- Attempt snapshots
- Timed attempts
- Autosave and resume
- Manual grading
- Result release controls
- Invigilator dashboard
- Exports
- Audit logs
- Backup and restore

## Documentation

- `docs/README.md`
- `docs/standalone-cbt-plan.md`
- `docs/project-status.md`
- `docs/extraction-checklist.md`

## Development Status

Standalone foundation, secure CBT kernel, deployment tooling, pilot setup, roster import, and question-bank management are in place. See `docs/project-status.md` for current progress and next steps.

## Pilot Setup

After configuring MySQL/MariaDB in `.env`, run:

```bash
php artisan migrate --seed
php artisan school:setup --name="Pilot School" --admin-email="admin@school.test"
```

If `--admin-password` is omitted, the command prints a generated password.

`school:setup` is idempotent and creates:

- roles and permissions
- the current academic session and three terms
- JSS 1 to SS 3 class levels with A, B, and C arms
- a starter subject catalog
- admin, exam officer, question author, invigilator, and grader accounts
- default question categories for each subject and class level

Starter staff accounts use `--staff-password`, which defaults to `staff12345`. Change it before live exams.

Generate a sample roster CSV:

```bash
php artisan school:setup --sample-roster
```

The sample is written to `storage/app/private/rosters/sample-students.csv` on the local disk.

Import students from CSV:

```bash
php artisan roster:import storage/app/rosters/students.csv --create-users --default-password=student123
```

Required CSV columns:

- `admission_number`
- `first_name`
- `last_name`

Optional CSV columns:

- `middle_name`
- `email`
- `username`
- `class_level`
- `section`
- `academic_session`
- `status`

Create a manual local backup:

```bash
php artisan backup:create
```

Restore a verified backup after migrating the target database schema:

```bash
php artisan backup:restore backups/YYYY-MM-DD_HHMMSS-ID/manifest.json
```

Check deployment health:

```bash
php artisan health:check
```

The same report is available at `/health` for browser or LAN monitoring checks.
