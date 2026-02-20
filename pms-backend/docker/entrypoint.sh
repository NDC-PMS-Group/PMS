#!/bin/sh
set -e

cd /var/www

if [ -f .env ]; then
  echo "Using existing .env file"
elif [ -f .env.docker ]; then
  cp .env.docker .env
  echo "Copied .env.docker -> .env"
elif [ -f .env.example ]; then
  cp .env.example .env
  echo "Copied .env.example -> .env"
fi

if [ -f .env ] && grep -q '^APP_KEY=$' .env; then
  php artisan key:generate --force || true
fi

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
  echo "Waiting for database ${DB_HOST:-db}:${DB_PORT:-3306}..."
  ATTEMPTS=0
  MAX_ATTEMPTS="${DB_WAIT_MAX_ATTEMPTS:-60}"

  until php -r '
  $host = getenv("DB_HOST") ?: "db";
  $port = getenv("DB_PORT") ?: "3306";
  $db = getenv("DB_DATABASE") ?: "ndc_pms";
  $user = getenv("DB_USERNAME") ?: "root";
  $pass = getenv("DB_PASSWORD") ?: "";
  try {
      new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass);
      exit(0);
  } catch (Throwable $e) {
      exit(1);
  }
  '; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
      echo "Database not ready after ${MAX_ATTEMPTS} attempts"
      exit 1
    fi
    sleep 2
  done

  echo "Database is ready"
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  php artisan db:seed --force
fi

php artisan storage:link || true

exec "$@"
