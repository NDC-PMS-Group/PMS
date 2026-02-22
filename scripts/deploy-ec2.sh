#!/usr/bin/env bash
set -euo pipefail

REMOTE="${REMOTE:-origin}"
BRANCH="${BRANCH:-main}"
APP_DIR="${APP_DIR:-$(pwd)}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
RUN_SEEDERS="${RUN_SEEDERS:-false}"
STASH_LOCAL_CHANGES="${STASH_LOCAL_CHANGES:-true}"

echo "Deploy start"
echo "APP_DIR=${APP_DIR}"
echo "REMOTE=${REMOTE}"
echo "BRANCH=${BRANCH}"

cd "${APP_DIR}"

if [[ ! -d .git ]]; then
  echo "Error: ${APP_DIR} is not a git repository."
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  if [[ "${STASH_LOCAL_CHANGES}" == "true" ]]; then
    STASH_NAME="pre-deploy-$(date +%Y%m%d-%H%M%S)"
    echo "Working tree not clean. Stashing local changes as ${STASH_NAME}."
    git stash push -u -m "${STASH_NAME}" >/dev/null
  else
    echo "Error: working tree not clean and STASH_LOCAL_CHANGES=false."
    exit 1
  fi
fi

git fetch "${REMOTE}" "${BRANCH}"
git checkout "${BRANCH}"
git pull --ff-only "${REMOTE}" "${BRANCH}"

docker compose up -d --build

if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
  docker compose exec -T backend php artisan migrate --force
fi

if [[ "${RUN_SEEDERS}" == "true" ]]; then
  docker compose exec -T backend php artisan db:seed --force
fi

docker compose ps
echo "Deploy done"
