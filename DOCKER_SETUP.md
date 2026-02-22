# NDC-PMS Docker Setup

This repo now supports two Docker modes:
- `prod-like mode` (rebuild images): mirrors deployment behavior.
- `dev mode` (hot reload): edit code and refresh without rebuilding.

## 1. Prerequisites
- Docker Desktop (Mac/Windows) or Docker Engine + Compose plugin (Linux)
- Port `80` available

## 2. Run locally (prod-like)
From the repository root (builds frontend static assets):

```bash
docker compose up -d --build
```

Open:
- `http://localhost`

## 3. Run locally (dev mode, recommended for coding)
This mode uses:
- Vite dev server for frontend hot reload.
- Mounted backend source for fast Laravel iteration.
- Gateway still exposed on `http://localhost:8080`.

Start:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

On first run (or when backend dependencies change), install backend deps:

```bash
docker compose exec backend composer install
```

Run migrations/seeders manually in dev mode:

```bash
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --force
```

## 4. Useful commands

```bash
# See container status
docker compose ps

# Follow logs
docker compose logs -f

# Stop stack
docker compose down

# Stop + remove DB data (DANGEROUS: deletes data)
docker compose down -v
```

For dev mode stop:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
```

## 5. Database defaults (docker-compose.yml)
- DB: `ndc_pms`
- User: `ndc`
- Password: `ndc_password`
- Root password: `root_password`

## 6. Notes
- Backend auto-runs migrations on startup (`RUN_MIGRATIONS=true`).
- Seeder auto-run is disabled by default (`RUN_SEEDERS=false`).
- Frontend calls API through same origin (proxy), so CORS problems are minimized.
- In dev mode, backend auto-migrations are disabled to avoid repeated startup failures during schema work.

## 7. First-time sanity checks

```bash
# backend health logs
docker compose logs backend --tail=100

# check DB tables
docker compose exec db mysql -undc -pndc_password -e "USE ndc_pms; SHOW TABLES;"
```
