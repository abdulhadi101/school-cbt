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

- `docs/standalone-cbt-plan.md`
- `docs/extraction-checklist.md`

## Development Status

Fresh Laravel application created. The CBT kernel is being rebuilt directly in this standalone project without retaining copied SMS reference files.

## Pilot Setup

After configuring MySQL/MariaDB in `.env`, run:

```bash
php artisan migrate --seed
php artisan school:setup --name="Pilot School" --admin-email="admin@school.test"
```

If `--admin-password` is omitted, the command prints a generated password.

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
