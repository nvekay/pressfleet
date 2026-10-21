#!/usr/bin/env bash
# Builds and starts the public demo on this server, then provisions the sites and registers them with the hub.
# Run from the repository root after infra/prod/init-env.sh has written .env.prod:
#   infra/prod/up.sh
set -euo pipefail
cd "$(dirname "$0")/../.."

if [ ! -f .env.prod ]; then
  echo "No .env.prod: run infra/prod/init-env.sh <domain> first" >&2
  exit 1
fi
compose() { docker compose -f infra/docker-compose.prod.yml --env-file .env.prod "$@"; }

# Fresh anonymous volumes give every site the WordPress, plugin and theme from the new image
compose up -d --build --renew-anon-volumes --remove-orphans --wait
PROD=1 bash infra/provision.sh
docker image prune -f >/dev/null

domain=$(grep -E '^DOMAIN=' .env.prod | cut -d= -f2-)
echo
echo "Hub:   https://hub.$domain"
echo "Sites: https://alder.$domain  https://birch.$domain  https://cedar.$domain"
