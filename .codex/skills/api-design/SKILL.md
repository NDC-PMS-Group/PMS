---
name: api-design
description: Design consistent REST APIs with status codes, versioning, pagination, filtering, searching, sorting, JSON response formats, and OpenAPI-ready contracts.
---

# API Design Skill

## When To Use

Use this skill when creating or changing API endpoints, request and response contracts, REST conventions, pagination, filtering, searching, sorting, versioning, and endpoint documentation.

## Best Practices

- Use nouns for resources and HTTP verbs for actions.
- Use nested routes only when the child resource depends on the parent context.
- Return `201` for created resources and `204` for successful empty deletes.
- Return `422` for validation errors and include field-level messages.
- Use consistent JSON envelopes or resource shapes already established in the project.
- Paginate list endpoints by default.
- Allow filtering, searching, and sorting only on explicit fields.
- Make response shapes OpenAPI-ready.
- Preserve backward compatibility when extending existing endpoints.
- Include stable identifiers and human-readable labels when useful for UI.

## Things To Avoid

- Do not encode actions as vague endpoint names when a REST resource fits.
- Do not expose internal table structures accidentally.
- Do not return different shapes for the same endpoint based on minor conditions.
- Do not accept arbitrary sort or filter fields.
- Do not break existing clients without a transition plan.

## Common Mistakes

- Returning `200` for everything.
- Missing pagination metadata.
- Mixing camelCase and snake_case without a deliberate convention.
- Adding search behavior that scans too many columns without indexes.
- Returning authorization failures as `404` inconsistently.

## Practical Checklist

- Route name and method match REST conventions.
- Request fields are documented.
- Response fields are stable and consistent.
- Status codes are correct.
- Pagination, filters, search, and sorting are allowlisted.
- Errors are predictable.
- Endpoint can be documented in OpenAPI without ambiguity.

## Production Recommendations

- Version APIs when breaking changes are expected.
- Monitor endpoint latency and payload size.
- Keep list responses compact.
- Use resource includes only when needed and authorized.
- Document auth, permissions, examples, and error responses.

