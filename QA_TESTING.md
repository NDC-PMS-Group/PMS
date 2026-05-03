# QA Testing Strategy

This project now uses 2 testing layers:

## 1) Unit tests (fast)
- Location: `pms-backend/tests/Unit`
- Purpose: verify isolated logic (models/services/utils).
- CI: runs on every PR to `main`.

## 2) Integration/API tests
- Location: `pms-backend/tests/Feature`
- Purpose: verify real endpoints + DB behavior (auth, permissions, workflows).
- CI: runs on every PR to `main`.

## Local commands

Backend tests:

```bash
cd pms-backend
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```
