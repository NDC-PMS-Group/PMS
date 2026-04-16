#!/usr/bin/env bash
set -euo pipefail

docker compose exec -T backend php artisan db:seed --class=Database\\Seeders\\CloudDemoDataSeeder --force
