#!/usr/bin/env bash
# Installs WordPress on the three demo sites, activates the PressFleet plugin and theme, gives each site its own
# brand (a theme style variation) and creates an Application Password for the hub. Safe to run again.
#   pnpm dev:wp            local sites on localhost:8081-8083
#   infra/prod/up.sh       the public demo (PROD=1, DOMAIN from .env.prod)
set -euo pipefail
cd "$(dirname "$0")"

if [ "${PROD:-}" = 1 ]; then
  ENV_FILE=../.env.prod
  compose() { docker compose -f docker-compose.prod.yml --env-file "$ENV_FILE" "$@"; }
else
  ENV_FILE=../.env
  compose() { docker compose "$@"; }
fi
setting() { grep -E "^$1=" "$ENV_FILE" | cut -d= -f2- || true; }

ADMIN_PASSWORD=$(setting WP_ADMIN_PASSWORD)
if [ -z "$ADMIN_PASSWORD" ]; then
  echo "Set WP_ADMIN_PASSWORD in ${ENV_FILE#../}" >&2
  exit 1
fi
DOMAIN=$(setting DOMAIN)

TITLES=("Alder Physio" "Birch Dental" "Cedar Vision")
STYLES=("alder" "birch" "cedar")
sites_json="["

for i in 1 2 3; do
  service="site$i"
  style="${STYLES[$((i - 1))]}"
  title="${TITLES[$((i - 1))]}"
  if [ "${PROD:-}" = 1 ]; then
    url="https://$style.$DOMAIN"
    # Leads from the form block go to the hub through Caddy
    hub_leads="https://hub.$DOMAIN/api/leads"
  else
    url="http://localhost:808$i"
    # Leads from the form block go to the hub API on the host
    hub_leads="${HUB_LEADS_URL:-http://host.docker.internal:4000/leads}"
  fi
  wp() { compose exec -T -u www-data "$service" wp "$@"; }

  if ! wp core is-installed 2>/dev/null; then
    wp core install --url="$url" --title="$title" --admin_user=admin --admin_password="$ADMIN_PASSWORD" \
      --admin_email="admin@site$i.pressfleet.test" --skip-email
  fi
  wp rewrite structure '/%postname%/' >/dev/null
  wp plugin activate pressfleet-connector >/dev/null
  wp theme activate pressfleet-theme >/dev/null
  wp option update pressfleet_hub_url "$hub_leads" >/dev/null
  # Brand, pages and case studies (infra/seed-site.php runs inside WordPress)
  wp eval-file - "$style" --user=admin < seed-site.php

  # One Application Password per site for the hub; recreated on every run so the file stays current
  wp user application-password delete admin --all >/dev/null 2>&1 || true
  app_password=$(wp user application-password create admin pressfleet-hub --porcelain)
  secret=$(wp pressfleet secret)
  sites_json+="{\"name\":\"$title\",\"url\":\"$url\",\"username\":\"admin\",\"applicationPassword\":\"$app_password\",\"secret\":\"$secret\"}"
  [ "$i" -lt 3 ] && sites_json+=","
  echo "$title: $url (admin)"
done
sites_json+="]"

if [ "${PROD:-}" = 1 ]; then
  # The demo API refuses new sites over HTTP, so they are written to its database directly
  echo "$sites_json" | compose exec -T api node dist/cli/register-sites.js
else
  echo "$sites_json" > .sites.json
  echo "Wrote infra/.sites.json for the hub"
fi
