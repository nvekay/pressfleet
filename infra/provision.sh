#!/usr/bin/env bash
# Installs WordPress on the three demo sites, activates the PressFleet plugin and theme, gives each site its own
# brand (a theme style variation) and creates an Application Password for the hub. Safe to run again.
#   pnpm dev:wp
set -euo pipefail
cd "$(dirname "$0")"

ADMIN_PASSWORD=$(grep -E '^WP_ADMIN_PASSWORD=' ../.env | cut -d= -f2-)
if [ -z "$ADMIN_PASSWORD" ]; then
  echo "Set WP_ADMIN_PASSWORD in .env" >&2
  exit 1
fi

TITLES=("Alder Physio" "Birch Dental" "Cedar Vision")
STYLES=("alder" "birch" "cedar")
sites_json="["

for i in 1 2 3; do
  service="site$i"
  url="http://localhost:808$i"
  title="${TITLES[$((i - 1))]}"
  wp() { docker compose exec -T -u www-data "$service" wp "$@"; }

  if ! wp core is-installed 2>/dev/null; then
    wp core install --url="$url" --title="$title" --admin_user=admin --admin_password="$ADMIN_PASSWORD" \
      --admin_email="admin@site$i.pressfleet.test" --skip-email
  fi
  wp rewrite structure '/%postname%/' >/dev/null
  wp plugin activate pressfleet-connector >/dev/null
  wp theme activate pressfleet-theme >/dev/null
  # Leads from the form block go to the hub API on the host
  wp option update pressfleet_hub_url "${HUB_LEADS_URL:-http://host.docker.internal:4000/leads}" >/dev/null
  # Brand, pages and case studies (infra/seed-site.php runs inside WordPress)
  wp eval-file - "${STYLES[$((i - 1))]}" --user=admin < seed-site.php

  # One Application Password per site for the hub; recreated on every run so the file stays current
  wp user application-password delete admin --all >/dev/null 2>&1 || true
  app_password=$(wp user application-password create admin pressfleet-hub --porcelain)
  secret=$(wp pressfleet secret)
  sites_json+="{\"name\":\"$title\",\"url\":\"$url\",\"username\":\"admin\",\"applicationPassword\":\"$app_password\",\"secret\":\"$secret\"}"
  [ "$i" -lt 3 ] && sites_json+=","
  echo "$title: $url (admin)"
done

echo "${sites_json}]" > .sites.json
echo "Wrote infra/.sites.json for the hub"
