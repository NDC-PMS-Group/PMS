---
name: security
description: Apply secure Laravel and Vue practices for XSS, SQL injection, CSRF, authentication, authorization, file uploads, rate limiting, sensitive data, and OWASP risks.
---

# Security Skill

## When To Use

Use this skill for authentication, authorization, permissions, file uploads, sensitive data handling, validation, public endpoints, CORS, CSRF, rate limiting, password handling, and security reviews.

## Best Practices

- Enforce authorization on the backend for every protected action.
- Use Laravel policies, gates, middleware, and permission checks consistently.
- Use Eloquent/query builder bindings to prevent SQL injection.
- Escape user content in Vue and avoid raw HTML unless sanitized.
- Validate file type, extension, size, storage location, and ownership.
- Store passwords only through Laravel hashing.
- Protect tokens, session cookies, API keys, and credentials.
- Apply rate limits to login, password reset, upload, and public submission endpoints.
- Keep CSRF and Sanctum settings aligned with frontend origins.
- Log security-relevant events without exposing secrets.
- Follow OWASP guidance for input validation, authentication, access control, and secure configuration.

## Things To Avoid

- Do not trust role or user IDs sent by the client.
- Do not expose hidden fields as authorization controls.
- Do not log credentials, tokens, raw files, or sensitive payloads.
- Do not store uploads in executable public paths.
- Do not return private user data in broad list endpoints.
- Do not disable CSRF, CORS, or auth checks to make local testing easier.

## Common Mistakes

- Checking authentication but not object ownership.
- Validating `exists` without checking permission to use that record.
- Allowing unrestricted upload MIME types.
- Rendering server-provided HTML directly in Vue.
- Returning too much user or audit information in API resources.

## Practical Checklist

- Endpoint requires the correct auth guard.
- User is authorized for the exact resource.
- Input is validated and normalized.
- SQL uses bindings or Eloquent.
- Uploads are restricted and scanned by metadata rules.
- Responses do not leak sensitive fields.
- Errors do not expose internals.
- Rate limits are appropriate.

## Production Recommendations

- Rotate leaked or shared credentials immediately.
- Use HTTPS everywhere.
- Use secure cookie settings in production.
- Keep dependencies patched.
- Review OWASP Top 10 risks during major changes.
- Monitor logs for repeated auth failures and suspicious activity.

