# Browser Testing Scenarios

This document describes the browser tests for the School CBT application.

## Overview

Our browser tests use Pest PHP with Laravel's browser testing capabilities. Tests run in headless mode and verify that pages load correctly without JavaScript errors.

**Note:** Browser tests in this project focus on public pages that don't require authentication. Authenticated workflows are better tested using Feature tests with HTTP assertions.

## Test Files

### 1. WelcomePageTest.php
**Critical Workflow: Application Entry Point**

Tests that the welcome page loads correctly and is accessible.

| Test Case | Description | Workflow Steps |
|-----------|-------------|----------------|
| `test_welcome_page_has_login_button` | Welcome page has login link | 1. Visit homepage<br>2. Verify "Log in" link appears<br>3. No JavaScript errors |
| `test_welcome_page_has_laravel_logo` | Welcome page shows Laravel branding | 1. Visit homepage<br>2. Verify "Laravel" text appears<br>3. No JavaScript errors |
| `test_welcome_page_loads_quickly` | Performance: page loads fast | 1. Visit homepage<br>2. Measure load time<br>3. Verify under 5 seconds |

**Why Critical:** First impression for users, must work flawlessly.

---

### 2. AuthenticationTest.php
**Critical Workflow: Login Form**

Tests the login page displays correctly with all form elements.

| Test Case | Description | Workflow Steps |
|-----------|-------------|----------------|
| `test_login_page_loads_with_form_elements` | Login page has all required fields | 1. Visit /login<br>2. Verify Email field<br>3. Verify Password field<br>4. Verify Remember me checkbox<br>5. Verify Log in button<br>6. Verify Forgot password link |
| `test_login_form_has_proper_input_fields` | Form inputs have correct types | 1. Visit /login<br>2. Verify email input type<br>3. Verify password input type<br>4. No JavaScript errors |
| `test_login_page_has_register_link` | New users can find registration | 1. Visit /login<br>2. Verify Register link appears<br>3. No JavaScript errors |

**Why Critical:** Authentication is the entry point for all users.

---

## Why Browser Tests Are Limited

Browser tests in this project are intentionally simple because:

1. **Authentication Complexity**: Browser tests with authentication require complex setup (database migrations, user creation, role assignment)
2. **Feature Tests Are Better**: Authenticated workflows are better tested using Feature tests with `$this->actingAs()` and HTTP assertions
3. **Speed**: Browser tests are slower than Feature tests
4. **Maintenance**: Browser tests break easily when UI changes

## Recommended Testing Strategy

| Test Type | Use Case | Example |
|-----------|----------|---------|
| **Browser Tests** | Public pages, UI smoke tests | Welcome page, Login form |
| **Feature Tests** | Authenticated workflows, API endpoints | Dashboard, Exam taking, Grading |
| **Unit Tests** | Business logic, services | Grading algorithms, Result calculations |

## Running Browser Tests

```bash
# Run all browser tests
php artisan test --testsuite=Browser

# Run specific test file
php artisan test tests/Browser/WelcomePageTest.php

# Run specific test method
php artisan test --filter=test_welcome_page_has_login_button

# Run with visible browser (non-headless)
php artisan test --testsuite=Browser --no-headless
```

## Best Practices

1. **Keep It Simple**: Browser tests should verify pages load, not complex workflows
2. **No Authentication**: Avoid testing authenticated pages with browser tests
3. **Performance**: Include load time assertions for critical pages
4. **No JavaScript Errors**: Always verify no JS errors occur
5. **Use Feature Tests**: For authenticated workflows, use Feature tests instead

## Future Enhancements

If browser tests for authenticated workflows are needed:

1. Create a `BrowserTestCase` base class with proper database setup
2. Add helper methods for creating users and logging in
3. Use `RefreshDatabase` trait for each test
4. Consider using Laravel Dusk for more advanced browser testing

## Related Documentation

- See `tests/Feature/` for authenticated workflow tests
- See `tests/Pest.php` for test configuration
- See `phpunit.xml` for test suite configuration
