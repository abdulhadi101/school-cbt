# SMS CBT Extraction Checklist

## Purpose

This file lists what can be copied from the current SMS as reference material and what must be rewritten for the standalone CBT.

The copied files should initially live under `_reference/sms-cbt/` and should not be treated as production-ready code.

## Copy As Reference

### Backend Models

- `app/Models/CbtExam.php`
- `app/Models/CbtQuestion.php`
- `app/Models/CbtAttempt.php`
- `app/Models/CbtAttemptAnswer.php`
- `app/Models/CbtManualGrade.php`

### Backend Controllers

- `app/Http/Controllers/Admin/CbtExamController.php`
- `app/Http/Controllers/Admin/CbtQuestionController.php`
- `app/Http/Controllers/Admin/CbtQuestionBankController.php`
- `app/Http/Controllers/Admin/CbtGradingController.php`
- `app/Http/Controllers/Student/CbtController.php`

### Migrations

- `database/migrations/tenant/*cbt*.php`

### Web Pages

- `resources/js/pages/Admin/cbt/**`
- `resources/js/pages/student/cbt/**`

### Views

- `resources/views/admin/cbt/pdf/**`
- `resources/views/admin/cbt/word/**`

### Tests

- `tests/Feature/App/Api/StudentApiTest.php`
- `tests/Feature/App/Web/Admin/CbtEssayGradingTest.php`
- `tests/Feature/App/Web/Admin/CbtEssayReviewGateTest.php`
- `tests/Feature/App/Web/Admin/CbtStaffPermissionMappingTest.php`
- `tests/Feature/App/Web/Security/Phase5RegressionTest.php`

## Rewrite Instead Of Copying Directly

- Database schema
- Multi-tenancy assumptions
- User/student/staff relationships
- Permission checks
- Published exam mutability rules
- Question-bank model
- Attempt lifecycle
- Scoring denominator logic
- Essay grading state transitions
- Result-release serialization
- Autosave conflict handling
- Submission transactions
- Question and option randomization
- Parent result handling
- Mobile API contracts
- AI generation and AI grading
- Global question-bank synchronization

## Known SMS CBT Issues To Avoid

- Active web attempt payload exposes `correct_options`.
- Unanswered questions are excluded from score denominator.
- Essay submissions can be marked `graded` before manual grading is complete.
- Question routes do not enforce nested question ownership.
- Parent payloads can expose answer keys.
- Result hiding is UI-level instead of server-level.
- Published questions can be modified after attempts exist.
- Web shuffling changes on refresh.
- Mobile question contracts differ from backend payloads.
- AI materials are stored publicly in the SMS.

## First Porting Strategy

1. Copy reference files into `_reference/sms-cbt/`.
2. Build fresh standalone models and migrations from the improved schema.
3. Port only useful validation and UI ideas.
4. Add tests for each SMS defect before implementing the new behavior.
5. Keep the SMS repo available as reference until the standalone CBT has equivalent workflows.

## First Production-Quality Tests

- Active attempt does not expose answer keys.
- Parent/student result payloads obey release policy.
- Unanswered questions count as zero in final percentage.
- Mixed objective/essay attempts enter `grading`.
- Attempt cannot be started twice concurrently.
- Submit can be retried safely.
- Autosave cannot overwrite a newer answer with an older request.
- Published revision cannot be changed silently.
- Attempt snapshot remains stable after question-bank edits.
- Result exports match stored attempts.
