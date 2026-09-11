# School CBT Standalone CBT Plan

## Goal

Build a lightweight, standalone CBT application for a single school. The pilot target is 120 concurrent students on a local school LAN, with MySQL/MariaDB as the primary database and no dependency on the existing SMS application at runtime.

## Product Scope

Version one should include only the features required to reliably prepare, deliver, grade, and report school CA tests and exams.

### Include In Version One

- Local school setup
- Local administrator account
- Staff and student management
- Classes, sections, subjects, academic sessions, and terms
- CSV/XLSX roster import
- Independent question bank
- Question categories, tags, and difficulty
- Question versioning
- Exam drafts, review, approval, and publication
- Immutable published exam revisions
- Attempt-specific question snapshots
- Timed attempts
- Autosave and resume
- Server-side expiry
- Manual grading for essay questions
- Result release controls
- Invigilator dashboard
- CSV/XLSX result exports
- Audit logs
- Local backup and restore

### Exclude From Version One

- Multi-school SaaS tenancy
- Platform billing and subscriptions
- Paystack
- AI question generation
- AI essay grading
- SMS messaging
- Email dependency
- Cloud storage dependency
- Parent portal
- Mobile application requirement
- Global/shared question bank
- Government/group management features
- Moodle plugin compatibility

## Recommended Stack

- Backend: Laravel/PHP
- Frontend: Vue 3 and TypeScript
- Database: MySQL/MariaDB
- Runtime target: local Linux server or Linux VM
- Web server: FrankenPHP candidate, with Nginx/PHP-FPM as fallback
- Storage: local filesystem
- Queue/cache: database for pilot, Redis optional later
- Access: browser over school LAN

## Pilot Hardware Target

- CPU: 6 cores minimum, 8 preferred
- RAM: 16 GB
- Disk: 256 GB SSD
- Network: Gigabit Ethernet
- Backup: 1 TB external drive plus optional cloud backup
- Power: UPS strongly recommended

## Architecture

Use a modular monolith. Do not split into microservices for the first release.

Core modules:

- Identity and permissions
- School profile
- Academic catalog
- Question bank
- Exam authoring
- Exam delivery
- Attempt management
- Grading
- Results and reports
- Invigilation
- Audit
- Backup and restore
- System administration

## Database Direction

Use a single MySQL/MariaDB database and a normal application schema. Do not carry over central/tenant schema switching from the SMS.

Important tables to design cleanly:

- users
- roles
- permissions
- students
- staff
- academic_sessions
- terms
- class_levels
- sections
- subjects
- enrollments
- teaching_assignments
- question_bank_entries
- question_versions
- question_options
- question_categories
- question_tags
- exams
- exam_revisions
- exam_slots
- exam_audiences
- exam_accommodations
- attempts
- attempt_questions
- attempt_answers
- answer_revisions
- manual_grades
- result_releases
- audit_logs
- backups

## Critical Improvements Over The SMS CBT

### Immutable Exam Revisions

The SMS allows published questions to be modified or deleted. The standalone app must create locked exam revisions. Attempts must point to the exact revision used.

### Attempt Snapshots

At attempt start, persist:

- selected question version IDs
- question order
- option order
- question marks
- effective deadline
- exam revision ID

### Correct Scoring

Unanswered questions must count in the denominator. Attempts with essay/manual questions must not be finalized until all required manual grading is complete.

### Secure Result Release

Never send answer keys, correctness, explanations, or scores to student browsers unless the result-release policy allows it.

### Safer Autosave

Autosave must include client sequence numbers or revisions so older requests cannot overwrite newer answers.

### Transactional Submission

Submission should be idempotent and protected by transactions or locks.

### Strong Authorization

Every exam, question, grading, report, and invigilation action must check named permissions and resource ownership.

### Invigilation

Provide a live dashboard for not started, active, disconnected, submitted, expired, grading, and graded candidates.

### Backup And Restore

The school must be able to create, download, verify, and restore backups without developer help.

## Moodle-Inspired Features To Adopt

- Question-bank entries separated from versions
- Question status: draft, ready, retired
- Random question pools
- Attempt snapshots
- Review/release controls
- Per-student accommodations
- Manual grading workflows
- Item analysis in phase two
- Moodle XML/GIFT import in phase two
- Safe Exam Browser in phase two only

## Initial Question Types

- Single-answer MCQ
- Multiple-response MCQ
- True/false
- Short answer with multiple accepted answers
- Numerical with tolerance
- Fill-in-the-blank
- Essay

## Deployment Direction

Version one should work without internet after installation.

Deployment requirements:

- Unique generated app key
- MySQL/MariaDB configuration
- Local file storage
- Debug mode disabled
- Scheduled backups
- Restore command
- Health check page or command
- LAN URL documented
- No exposed database port on the school LAN unless explicitly configured

## Pilot Setup Flow

1. Create a MySQL/MariaDB database named `school_cbt`.
2. Configure `.env` with the database credentials.
3. Run `php artisan migrate --seed`.
4. Run `php artisan school:setup --name="Pilot School" --admin-email="admin@school.test"`.
5. Import the roster with `php artisan roster:import path/to/students.csv --create-users`.
6. Change generated/default passwords before live exams.
7. Create a manual backup with `php artisan backup:create`.
8. Restore a verified backup with `php artisan backup:restore backups/YYYY-MM-DD_HHMMSS-ID/manifest.json` after migrating the target schema.
9. Verify deployment health with `php artisan health:check` or `/health`.

Roster CSV columns:

- Required: `admission_number`, `first_name`, `last_name`
- Optional: `middle_name`, `email`, `username`, `class_level`, `section`, `academic_session`, `status`

For the pilot, CSV import is enough. XLSX import can be added later after the core screens are stable.

## First Milestone

The first milestone is not a full CBT UI. It is a secure exam kernel:

- question versioning
- exam revision publishing
- attempt snapshot creation
- answer autosave
- deterministic grading
- result-release filtering
- audit logging

Once this kernel is correct, the UI can be copied/adapted safely from the SMS.
