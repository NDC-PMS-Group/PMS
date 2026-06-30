---
name: deployment
description: Deploy Laravel and Vue safely with environment configuration, production optimization, cache commands, queue workers, scheduler, nginx/apache routing, and Docker compatibility.
---

# Deployment Skill

## When To Use

Use this skill for production deployment, shared hosting, VPS, Docker, environment variables, Laravel optimization commands, frontend builds, queue workers, scheduler, Apache/Nginx routing, storage links, migrations, and release verification.

## Best Practices

- Confirm target branch, commit, environment, and domain before deploying.
- Back up production databases before imports or risky migrations.
- Keep source code outside public web roots when possible.
- Point public web roots to Laravel `public` or the intended frontend build directory.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure `APP_URL`, frontend URL, CORS, Sanctum, database, mail, queue, cache, and storage values per environment.
- Run Composer install with production flags.
- Run frontend production builds with correct Vite environment variables.
- Run Laravel cache commands after env and config are final.
- Run migrations with `--force`.
- Configure queue workers and scheduler where needed.

## Things To Avoid

- Do not deploy dirty unrelated work.
- Do not overwrite unrelated apps on shared hosting.
- Do not run destructive seeders in production unless explicitly requested.
- Do not expose `.env`, source folders, storage internals, or vendor files publicly.
- Do not leave debug mode enabled.

## Common Mistakes

- Building frontend with localhost API URLs.
- Forgetting `storage:link` or public storage routing.
- Running config cache before writing the production `.env`.
- Missing queue worker setup for notifications or mail.
- Apache/Nginx rewrite rules that break SPA routing or API routes.

## Practical Checklist

- Correct branch and commit are deployed.
- Production `.env` is configured.
- Composer dependencies installed.
- Frontend build completed with production API URL.
- Migrations ran successfully.
- Config, route, and view caches are refreshed.
- Storage link or equivalent is working.
- Queue and scheduler requirements are handled.
- Site, login, API, storage assets, and critical workflows are smoke tested.

## Production Recommendations

- Use atomic releases when infrastructure allows.
- Keep backups and rollback instructions.
- Use process supervisors for queues.
- Use cron for Laravel scheduler.
- Keep deployment logs.
- Rotate credentials after sharing them in chat or tickets.
- For Docker, keep environment config outside images and run migrations as a release step.

