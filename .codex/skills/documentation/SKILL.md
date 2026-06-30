---
name: documentation
description: Create and maintain PHPDoc, component documentation, endpoint documentation, README updates, setup instructions, and architecture explanations.
---

# Documentation Skill

## When To Use

Use this skill for README updates, setup instructions, endpoint docs, architecture explanations, PHPDoc, component notes, deployment instructions, environment variable documentation, and onboarding material.

## Best Practices

- Document what future maintainers need to know, not what code already says plainly.
- Keep setup instructions current and executable.
- Document required environment variables without exposing secrets.
- Explain architecture decisions and workflow rules where they are non-obvious.
- For endpoints, include method, path, auth, permissions, request fields, response shape, status codes, and examples.
- For components, document purpose, props, emits, slots, and important states when useful.
- Keep docs close to the feature when local context matters.
- Update docs in the same change that alters behavior or deployment.

## Things To Avoid

- Do not paste secrets into documentation.
- Do not duplicate large blocks of code in docs.
- Do not document aspirational behavior as if it already exists.
- Do not leave stale commands after dependency or deployment changes.
- Do not over-comment simple code.

## Common Mistakes

- Missing migration or deployment notes.
- README setup steps that skip environment variables.
- Endpoint docs that omit validation errors.
- Component docs that omit permission-limited states.
- Architecture docs that do not mention ownership boundaries.

## Practical Checklist

- Setup steps are accurate.
- Environment variables are listed safely.
- API contracts are OpenAPI-ready.
- Architecture notes explain important decisions.
- Deployment steps include cache, migration, queue, scheduler, and storage details.
- Testing instructions are present.
- Documentation matches implemented behavior.

## Production Recommendations

- Keep a release note for breaking changes.
- Document rollback steps for risky deployments.
- Maintain examples for common API requests.
- Keep onboarding docs short but complete.
- Review documentation during code review for behavior-changing work.

