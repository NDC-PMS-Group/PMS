# Codex Project Instructions

## Project Overview

This repository is a Laravel API and Vue 3 frontend application for project management workflows. Treat it as a production business system: preserve existing behavior, favor maintainable changes, and keep security, data integrity, and user workflow continuity at the center of every task.

## Architecture

- Backend lives in `pms-backend` and uses Laravel, PHP 8+, MySQL, Eloquent models, controllers, form requests, resources, migrations, seeders, middleware, policies, and service-style application logic where already established.
- Frontend lives in `pms-frontend` and uses Vue 3, Composition API, `script setup`, Pinia, Vue Router, Axios, Vite, and reusable single-file components.
- The API is the source of truth for permissions, workflow state, validation, persistence, and business rules.
- The frontend should render state clearly, validate for user convenience, and never rely on client-side checks as the only enforcement layer.
- Prefer existing folders, naming patterns, helper functions, stores, resources, and component conventions before introducing new abstractions.

## Before Starting Any Task

- Understand the existing architecture and data flow before editing.
- Inspect related controllers, models, requests, resources, stores, routes, and components.
- Avoid duplicate code and reuse existing components, composables, services, policies, and validation rules.
- Preserve backward compatibility for existing API consumers and saved data.
- Keep changes scoped to the requested behavior.
- Do not rewrite unrelated files, generated assets, or user changes.
- Explain modifications clearly and provide realistic testing instructions.

## Coding Standards

- Write clear, explicit code with descriptive names.
- Keep methods focused and easy to test.
- Use framework features instead of custom plumbing when Laravel or Vue already provides the behavior.
- Prefer typed PHP signatures where the codebase supports them.
- Prefer `const`/`let`, typed TypeScript interfaces, and narrow component props in frontend code.
- Avoid hidden side effects, large conditionals, and copy-pasted business logic.
- Add comments only for non-obvious business rules or complex decisions.

## Naming Conventions

- Laravel classes use standard framework naming: `ProjectController`, `StoreProjectRequest`, `ProjectResource`, `ProjectPolicy`.
- Database tables use plural snake_case names.
- Columns use snake_case and should be explicit about meaning.
- API fields use consistent snake_case unless an existing endpoint uses another convention.
- Vue components use PascalCase filenames for components and clear domain names such as `ProjectApprovalTimeline.vue`.
- Composables use `useXyz` naming.
- Pinia stores use domain names such as `useProjectStore`.
- Route names, permission keys, statuses, and workflow identifiers must remain stable unless a migration and compatibility plan are included.

## Folder Structure

- Keep Laravel HTTP concerns under `app/Http`.
- Keep validation in Form Request classes when request validation is non-trivial.
- Keep API output shaping in API Resources.
- Keep migrations, seeders, and factories under `database`.
- Keep Vue pages under `src/pages`, shared components under `src/components`, stores under `src/store`, routes under `src/router`, and shared types under `src/types`.
- Do not add new top-level folders unless they match an established project need.

## Git Guidelines

- Commit only files related to the task.
- Do not stage unrelated dirty worktree changes.
- Use concise imperative commit messages, for example `Fix SOI section checklist rendering`.
- Mention migrations, breaking changes, deployment steps, or manual data fixes in the commit or PR notes.
- Avoid committing local environment files, secrets, dependency directories, caches, or generated build artifacts unless the project explicitly tracks them.

## API Conventions

- Use RESTful routes and Laravel resource controllers where practical.
- Return consistent JSON structures with predictable keys.
- Use correct HTTP status codes: `200`, `201`, `204`, `400`, `401`, `403`, `404`, `409`, `422`, and `500` where appropriate.
- Use pagination for list endpoints that can grow.
- Support filtering, searching, and sorting consistently.
- Shape responses with API Resources rather than exposing raw models for complex objects.
- Keep route behavior backward compatible unless the user explicitly requests a breaking change.

## Error Handling

- Validate input through Form Requests or dedicated validators.
- Return validation failures as `422` with field-level messages.
- Return authorization failures as `403`.
- Return missing resources as `404`.
- Avoid leaking stack traces, SQL details, tokens, file paths, or secrets.
- Log unexpected server-side failures with enough context to debug without exposing sensitive data to users.

## Validation Rules

- Validate on both client and server, with server validation as the authority.
- Keep validation rules close to the request or business operation.
- Validate IDs with existence checks and authorization checks.
- Validate enum-like fields against known values.
- Validate dates, file uploads, amounts, email addresses, phone numbers, and workflow transitions strictly.
- Preserve existing accepted payloads when adding new validation.

## Logging

- Log security-relevant events such as authentication failures, permission violations, and sensitive workflow transitions.
- Log failed background jobs and integration errors.
- Do not log passwords, tokens, private keys, raw uploaded files, or full personally sensitive payloads.
- Prefer structured context arrays in Laravel logs.

## Performance Expectations

- Avoid N+1 queries by using eager loading.
- Paginate large lists.
- Add indexes for frequently filtered or joined columns.
- Cache expensive stable data only when invalidation is understood.
- Keep frontend bundles split by route where practical.
- Use lazy loading for heavy pages, modals, charts, maps, and large assets.
- Avoid unnecessary reactive watchers and repeated API calls.

## Security Requirements

- Enforce authentication and authorization on the backend.
- Use policies, gates, middleware, and permission checks consistently.
- Prevent SQL injection by using Eloquent or query builder bindings.
- Prevent XSS by avoiding raw HTML rendering unless sanitized.
- Validate and restrict file uploads by MIME type, extension, size, storage path, and permissions.
- Protect sensitive data in logs, API responses, screenshots, and docs.
- Keep CSRF, CORS, and Sanctum settings aligned with the deployment environment.
- Follow OWASP recommendations for authentication, authorization, input validation, and session handling.

## UI/UX Expectations

- Build the actual usable workflow, not placeholder screens.
- Match the existing design system, spacing, colors, typography, and component behavior.
- Prefer dense, organized SaaS interfaces for operational tools.
- Include loading, empty, error, disabled, success, and permission-limited states.
- Keep forms clear, responsive, and forgiving.
- Use accessible labels, focus states, semantic HTML, and keyboard-friendly interactions.
- Ensure text does not overflow or overlap on mobile or desktop.
- Maintain dark mode compatibility when the surrounding UI supports it.

## Testing Requirements

- Add or update tests when changing backend business rules, validation, authorization, workflows, or shared behavior.
- Use Laravel feature tests for API behavior and integration flows.
- Use unit tests for isolated service logic when present.
- Verify frontend behavior through focused manual checks or component tests where the project supports them.
- Cover edge cases, validation failures, permission failures, empty states, and regression scenarios.
- Always report what tests were run and what was not run.

## Documentation Expectations

- Update README or local docs when setup, deployment, environment variables, API contracts, or developer workflows change.
- Add PHPDoc or inline docs only where they clarify non-obvious behavior.
- Keep endpoint documentation OpenAPI-ready: path, method, auth, request fields, response shape, and errors.
- Document migrations or one-time production data changes clearly.

## Review Checklist

- The change solves the requested problem and does not introduce unrelated behavior.
- Existing architecture and naming conventions are followed.
- Business rules remain centralized and enforceable on the backend.
- API responses and status codes are consistent.
- Validation and authorization are complete.
- Database changes include indexes, constraints, rollback safety, and migration order.
- Frontend states are complete: loading, empty, error, success, disabled, and unauthorized.
- UI is responsive, accessible, and visually consistent.
- Performance risks such as N+1 queries and excessive re-renders are addressed.
- Security risks such as XSS, SQL injection, insecure uploads, and data leaks are avoided.
- Tests or manual verification steps are provided.

