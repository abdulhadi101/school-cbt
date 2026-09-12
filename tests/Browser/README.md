# Browser Testing Scenarios

This document describes the browser tests for the School CBT application.

## Overview

Our browser tests use **Pest PHP v4** with **Playwright** for real browser testing. Tests run in headless Chrome and verify complete user workflows including form interactions and authentication.

**Key learning:** Use `type()` instead of `fill()` for Vue/Inertia forms - Playwright's native `fill()` doesn't work with Vue reactivity.

## Test Files

### 1. WelcomePageTest.php
**Critical Workflow: Application Entry Point**

| Test Case | What It Tests |
|-----------|---------------|
| `welcome page has login button` | Homepage loads with login link |
| `welcome page has Laravel logo` | Branding renders correctly |
| `welcome page loads quickly` | Performance under 5 seconds |

---

### 2. AuthenticationTest.php
**Critical Workflow: User Login/Logout**

| Test Case | Workflow Steps |
|-----------|----------------|
| `user can login with valid credentials` | 1. Visit /login<br>2. Type email and password<br>3. Press "Log in"<br>4. Assert redirect to /dashboard<br>5. Assert user name visible |
| `user sees error with invalid credentials` | 1. Visit /login<br>2. Type wrong credentials<br>3. Press "Log in"<br>4. Assert error message appears<br>5. Assert still on /login |
| `user can logout` | 1. Login as user<br>2. Click profile dropdown<br>3. Press "Log Out"<br>4. Assert redirect to / |

**Why Critical:** Authentication gates all functionality.

---

### 3. StaffWorkflowTest.php
**Critical Workflow: Staff Navigation and Access Control**

| Test Case | Workflow Steps |
|-----------|----------------|
| `staff can access exams page` | 1. Login as exam-officer<br>2. Visit /staff/exams<br>3. Assert "Exams" visible<br>4. No JS errors |
| `student can access exams page` | 1. Login as student (with Student profile)<br>2. Visit /student/exams<br>3. Assert "Exams" visible<br>4. No JS errors |
| `staff can access grading page` | 1. Login as grader<br>2. Visit /staff/grading<br>3. Assert "Grading" visible<br>4. No JS errors |
| `staff can access questions page` | 1. Login as question-author<br>2. Visit /staff/questions<br>3. Assert "Questions" visible<br>4. No JS errors |

**Why Critical:** Role-based access control must work correctly.

---

### 4. ExamCreationTest.php
**Critical Workflow: Exam Creation Form**

| Test Case | Workflow Steps |
|-----------|----------------|
| `staff can access create exam page` | 1. Login as question-author<br>2. Visit /staff/exams/create<br>3. Assert "Title", "Description" fields visible<br>4. No JS errors |
| `staff can fill exam creation form` | 1. Login as question-author<br>2. Visit /staff/exams/create<br>3. Assert "New Exam Draft" heading<br>4. Type in Title field<br>5. Type in Description field<br>6. Assert "Save Draft" button visible<br>7. No JS errors |

**Why Critical:** Exam creation is the foundation of the system.

---

## Test Data Setup

Each browser test creates its own isolated data:

1. **Permissions** seeded from `PermissionRegistry` in `beforeEach`
2. **Roles** created with proper permission assignments
3. **Users** created with hashed passwords
4. **Student profiles** created with required fields (first_name, last_name, admission_number)

## Key Learnings

| Issue | Solution |
|-------|----------|
| `fill()` doesn't work with Vue/Inertia forms | Use `type()` instead |
| `waitForReload()` doesn't exist | Use `pressAndWaitFor($button, $seconds)` |
| `assertSee()` can't find text in input fields | Assert on visible labels/headings, not input values |
| `assertUrlIs()` includes full URL | Use `assertPathIs()` for path-only matching |
| Roles need `label` column | Always include `label` when creating roles |
| Students need `first_name`, `last_name` | Include all required fields in Student factory |

## Running Browser Tests

```bash
# Run all browser tests
php artisan test --testsuite=Browser

# Run specific test file
php artisan test tests/Browser/AuthenticationTest.php

# Run specific test
php artisan test --filter="user can login"

# Run with visible browser (non-headless)
php artisan test --testsuite=Browser --no-headless

# Run all tests (feature + browser)
php artisan test
```

## Test Results

```
104 tests passed (93 feature + 11 browser)
443 assertions
~43 seconds total
```

## Best Practices

1. **Use `type()` for Vue forms** - `fill()` fails with Inertia/Vue reactivity
2. **Seed permissions** - Always use `PermissionRegistry` in `beforeEach`
3. **Create complete profiles** - Students need `first_name`, `last_name`
4. **Assert visible text** - Don't assert on input values, assert on labels
5. **Wait after navigation** - Use `wait(2)` after page transitions
6. **Check screenshots** - Failed tests save screenshots to `tests/Browser/Screenshots/`
