---
name: authentication
description: Implement and review authentication with Laravel Sanctum, JWT-compatible patterns, login, refresh tokens, roles, permissions, middleware, password reset, and email verification.
---

# Authentication Skill

## When To Use

Use this skill for login, logout, registration, Sanctum, JWT compatibility, refresh tokens, password reset, email verification, roles, permissions, middleware, guards, sessions, and auth-related UI/API behavior.

## Best Practices

- Use Laravel Sanctum patterns already established in the project.
- Keep authentication separate from authorization.
- Use middleware for authenticated route protection.
- Use policies, gates, or permission checks for resource access.
- Hash passwords with Laravel hashing utilities.
- Rate limit login and password reset attempts.
- Invalidate sessions or tokens on logout and sensitive account changes.
- Verify email when workflows require trusted addresses.
- Keep role and permission names stable.
- Return minimal user data needed by the frontend.

## Things To Avoid

- Do not store plaintext passwords or tokens.
- Do not trust frontend role checks.
- Do not expose permission-management endpoints without strict authorization.
- Do not return password reset tokens in API responses.
- Do not mix session and token auth without clear CORS/CSRF configuration.

## Common Mistakes

- Missing rate limits on login.
- Allowing inactive or unverified users into protected workflows.
- Checking role names in too many places instead of central permission logic.
- Forgetting to revoke tokens after password changes.
- CORS or Sanctum stateful domain mismatch in production.

## Practical Checklist

- Auth guard is correct.
- Login validates credentials safely.
- Passwords are hashed.
- Tokens/sessions are invalidated correctly.
- Middleware protects routes.
- Roles and permissions are enforced server-side.
- Password reset and email verification flows are secure.
- Frontend handles unauthenticated and forbidden states.

## Production Recommendations

- Use HTTPS and secure cookies.
- Configure Sanctum stateful domains and CORS per environment.
- Rotate exposed credentials.
- Monitor repeated auth failures.
- Keep admin/permission actions audited.

