---
name: testing
description: Plan and implement backend PHPUnit tests, Laravel feature and unit tests, frontend component/API tests, validation tests, edge cases, and regression coverage.
---

# Testing Skill

## When To Use

Use this skill when adding or updating tests, designing a test plan, verifying bug fixes, covering validation, authorization, workflows, API behavior, frontend behavior, edge cases, and regressions.

## Best Practices

- Use Laravel feature tests for API endpoints and workflow behavior.
- Use unit tests for isolated services, helpers, and pure business logic.
- Test authorization separately from validation and happy paths.
- Test validation failures with specific field expectations.
- Use factories and seed only what each test needs.
- Keep tests deterministic and independent.
- Prefer behavior-focused assertions over implementation details.
- For frontend work, test components or stores where tooling exists and provide manual verification otherwise.

## Things To Avoid

- Do not rely on production data for automated tests.
- Do not write tests that depend on execution order.
- Do not mock the code under test so heavily that behavior is no longer verified.
- Do not ignore failing tests unrelated to your change without reporting them.
- Do not skip regression coverage for bug fixes touching shared behavior.

## Common Mistakes

- Only testing the happy path.
- Forgetting unauthenticated and unauthorized cases.
- Missing edge cases for empty lists, null values, long text, and invalid dates.
- Asserting exact full JSON payloads that make tests brittle.
- Not testing database side effects after API calls.

## Practical Checklist

- Happy path is covered.
- Validation failures are covered.
- Unauthorized and forbidden cases are covered.
- Missing resource case is covered.
- Database side effects are asserted.
- Edge cases and regression scenario are included.
- Manual test steps are documented when automation is not available.

## Production Recommendations

- Run targeted tests during development and broader suites before release.
- Keep test fixtures small and meaningful.
- Add regression tests for production bugs.
- Include migration and rollback testing for schema changes.
- Report exact commands run and any tests not run.

