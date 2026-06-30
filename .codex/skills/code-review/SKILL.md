---
name: code-review
description: Review code for readability, maintainability, SOLID, DRY, naming, architecture, security, performance, complexity, dead code, and duplicated logic.
---

# Code Review Skill

## When To Use

Use this skill when reviewing diffs, pull requests, architecture changes, bug fixes, security-sensitive changes, performance-sensitive changes, or large refactors.

## Best Practices

- Lead with findings ordered by severity.
- Reference exact files and lines when possible.
- Focus on correctness, maintainability, security, performance, and missing tests.
- Distinguish confirmed issues from questions or preferences.
- Keep style feedback limited to project standards and readability.
- Verify behavior against the user request and existing architecture.
- Look for backward compatibility risks.

## Things To Avoid

- Do not bury serious issues under summaries.
- Do not nitpick harmless style when larger risks exist.
- Do not request abstractions without clear benefit.
- Do not assume intent when code or tests can confirm behavior.
- Do not recommend broad rewrites for narrow issues.

## Common Mistakes

- Missing authorization regressions.
- Accepting duplicated logic that will drift.
- Ignoring N+1 queries in resources.
- Missing validation gaps.
- Overlooking dead code after refactors.
- Failing to check empty, error, and permission-limited UI states.

## Practical Checklist

- Code is readable and named clearly.
- Architecture follows existing patterns.
- Logic is not unnecessarily complex.
- SOLID and DRY principles are applied pragmatically.
- No duplicated business rules.
- No dead code or stale imports.
- Security checks are complete.
- Performance risks are considered.
- Tests cover meaningful behavior.

## Production Recommendations

- Require tests for workflow, authorization, validation, and migration changes.
- Check deployment and rollback impact.
- Verify logs do not expose secrets.
- Check database indexes for new filters and joins.
- Confirm UI changes are responsive and accessible.

