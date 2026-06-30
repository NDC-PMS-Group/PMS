---
name: laravel-api
description: Build and maintain Laravel API features using resource controllers, form requests, resources, authorization, transactions, and clean architecture.
---

# Laravel API Skill

## When To Use

Use this skill for Laravel API work involving controllers, routes, models, requests, resources, services, repositories, validation, authorization, middleware, pagination, filtering, searching, sorting, transactions, and exception handling.

## Best Practices

- Prefer Laravel conventions before custom architecture.
- Use resource controllers for CRUD-style endpoints.
- Use Form Requests for validation and authorization when request rules are more than trivial.
- Use API Resources to control response shape and avoid leaking internal model details.
- Keep controllers thin: validate, authorize, call domain logic, return a response.
- Use services for business workflows that span models or external systems.
- Use repositories only when they add real value, such as reusable complex queries or storage abstraction.
- Wrap multi-write operations in database transactions.
- Eager load relationships needed by resources.
- Use policies and middleware for authorization.
- Paginate large collections and expose consistent metadata.
- Implement filtering, searching, and sorting through explicit allowlists.
- Let Laravel exception handling produce consistent API errors, customizing only when needed.

## Things To Avoid

- Do not put complex business workflows directly in controllers.
- Do not return raw models from complex or sensitive endpoints.
- Do not trust frontend validation.
- Do not concatenate SQL strings with user input.
- Do not add repositories just to mirror Eloquent methods.
- Do not change API response contracts without compatibility planning.

## Common Mistakes

- Missing authorization checks on update, delete, export, or file endpoints.
- N+1 queries caused by resources accessing unloaded relationships.
- Validation rules that check existence but not ownership or permission.
- Inconsistent status codes for validation, authorization, and missing resources.
- Catching broad exceptions and hiding useful logs.

## Practical Checklist

- Route is authenticated and authorized.
- Request payload is validated.
- Controller is small and readable.
- Business logic is reusable when needed.
- Writes are transactional when multiple tables are involved.
- Response uses a Resource or consistent JSON structure.
- Lists are paginated and filter fields are allowlisted.
- Errors return correct status codes.
- Tests cover success, validation failure, unauthorized access, and missing resource cases.

## Production Recommendations

- Cache config and routes in production.
- Monitor slow queries and add indexes for frequent filters.
- Use queues for slow side effects such as email, notifications, imports, and exports.
- Keep audit logs for sensitive workflow transitions.
- Avoid returning stack traces or internal exception details.

