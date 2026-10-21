#!/usr/bin/env bash
# Writes .env.prod with fresh random secrets. DOMAIN is your domain (with DNS records for hub, alder, birch and
# cedar pointing at this server) or, without one, the server's IP as <a-b-c-d>.sslip.io.
#   infra/prod/init-env.sh 203-0-113-10.sslip.io
set -euo pipefail
cd "$(dirname "$0")/../.."

domain=${1:?Usage: infra/prod/init-env.sh <domain>}
if [ -f .env.prod ]; then
  echo ".env.prod already exists; edit it instead" >&2
  exit 1
fi
random() { openssl rand -hex 24; }

umask 077
cat > .env.prod <<EOF
DOMAIN=$domain
# The demo sites' admin user (only you need it, for the Site Editor)
WP_ADMIN_PASSWORD=$(random)
MARIADB_ROOT_PASSWORD=$(random)
MARIADB_PASSWORD=$(random)
POSTGRES_PASSWORD=$(random)
API_INTERNAL_KEY=$(random)
SECRETS_KEY=$(openssl rand -base64 32)
# Shown as a link in the dashboard header
GITHUB_URL=
# New leads go to this Telegram chat when both are set
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
EOF
echo "Wrote .env.prod for $domain"
