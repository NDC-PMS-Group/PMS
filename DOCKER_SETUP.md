# NDC-PMS Docker Setup

This runs the full stack with one public URL:
- `gateway` (Nginx reverse proxy) on port `80`
- `frontend` (Vue static build)
- `backend` (Laravel)
- `db` (MySQL 8)

## 1. Prerequisites
- Docker Desktop (Mac/Windows) or Docker Engine + Compose plugin (Linux)
- Port `80` available

## 2. Run locally
From the repository root:

```bash
docker compose up -d --build
```

Open:
- `http://localhost`

## 3. Useful commands

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

## 4. Database defaults (docker-compose.yml)
- DB: `ndc_pms`
- User: `ndc`
- Password: `ndc_password`
- Root password: `root_password`

## 5. Notes
- Backend auto-runs migrations on startup (`RUN_MIGRATIONS=true`).
- Seeder auto-run is disabled by default (`RUN_SEEDERS=false`).
- Frontend calls API through same origin (proxy), so CORS problems are minimized.

## 6. First-time sanity checks

```bash
# backend health logs
docker compose logs backend --tail=100

# check DB tables
docker compose exec db mysql -undc -pndc_password -e "USE ndc_pms; SHOW TABLES;"
```
