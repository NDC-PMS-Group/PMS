# QA Testing Strategy

This project now uses 3 testing layers:

## 1) Unit tests (fast)
- Location: `pms-backend/tests/Unit`
- Purpose: verify isolated logic (models/services/utils).
- CI: runs on every PR to `main`.

## 2) Integration/API tests
- Location: `pms-backend/tests/Feature`
- Purpose: verify real endpoints + DB behavior (auth, permissions, workflows).
- CI: runs on every PR to `main`.

## 3) E2E/Staging tests (Playwright)
- Location: `pms-frontend/e2e/staging.spec.ts`
- Purpose: simulate user flow on staging:
  - login
  - create project
  - verify pending approvals
- CI: `.github/workflows/e2e-staging.yml`
  - runs manually (`workflow_dispatch`)
  - runs automatically after successful `Deploy to EC2` workflow

## Required GitHub Secrets for E2E/Staging
- `STAGING_BASE_URL` (example: `http://174.129.149.39:8080`)
- `STAGING_USER_EMAIL`
- `STAGING_USER_PASSWORD`

## Local commands

Backend tests:

```bash
cd pms-backend
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

Staging E2E:

```bash
cd pms-frontend
STAGING_BASE_URL=http://174.129.149.39:8080 \
STAGING_USER_EMAIL=sa@gmail.com \
STAGING_USER_PASSWORD='Password123!' \
npx playwright test --config=playwright.config.ts
```

