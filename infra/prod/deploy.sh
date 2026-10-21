#!/usr/bin/env bash
# Ships the committed code to the server and starts it there. The server keeps its .env.prod between deploys.
#   infra/prod/deploy.sh root@203.0.113.10
set -euo pipefail
cd "$(dirname "$0")/../.."

target=${1:?Usage: infra/prod/deploy.sh <user@host>}
if [ -n "$(git status --porcelain)" ]; then
  echo "Uncommitted changes are not deployed (git archive ships HEAD)" >&2
fi

git archive --format=tar HEAD | ssh "$target" 'set -e
  dir=/opt/pressfleet
  next=$(mktemp -d)
  tar -x -C "$next"
  if [ -f "$dir/.env.prod" ]; then cp -p "$dir/.env.prod" "$next/"; fi
  chmod 755 "$next"
  rm -rf "$dir.old"
  if [ -d "$dir" ]; then mv "$dir" "$dir.old"; fi
  mv "$next" "$dir"
  rm -rf "$dir.old"'

if ssh "$target" 'test -f /opt/pressfleet/.env.prod'; then
  ssh "$target" 'bash /opt/pressfleet/infra/prod/up.sh'
else
  echo "Code is on the server. Next: ssh $target 'bash /opt/pressfleet/infra/prod/init-env.sh <domain>', then run this again."
fi
