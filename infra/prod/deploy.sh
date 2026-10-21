#!/usr/bin/env bash
# Builds the images on this machine, ships them and the committed code to the server, and starts them there, so a
# small server never has to build. The server keeps its .env.prod between deploys.
#   infra/prod/deploy.sh root@203.0.113.10
set -euo pipefail
cd "$(dirname "$0")/../.."

target=${1:?Usage: infra/prod/deploy.sh <user@host>}
if [ -n "$(git status --porcelain)" ]; then
  echo "Uncommitted changes are not deployed (the code is shipped from HEAD)" >&2
fi

case "$(ssh "$target" uname -m)" in
  aarch64 | arm64) platform=linux/arm64 ;;
  *) platform=linux/amd64 ;;
esac
github=$(git remote get-url origin 2>/dev/null | sed -E 's#^git@github.com:#https://github.com/#; s#\.git$##' || true)

# Builds from a clean export of HEAD, the same code the server gets
src=$(mktemp -d)
trap 'rm -rf "$src"' EXIT
git archive --format=tar HEAD | tar -x -C "$src"
docker build --platform "$platform" -f "$src/apps/api/Dockerfile" -t pressfleet-api "$src"
docker build --platform "$platform" -f "$src/apps/web/Dockerfile" --build-arg NEXT_PUBLIC_GITHUB_URL="$github" \
  -t pressfleet-web "$src"
docker build --platform "$platform" -f "$src/infra/wordpress/Dockerfile.prod" -t pressfleet-wordpress-prod "$src"

echo "Uploading images..."
docker save pressfleet-api pressfleet-web pressfleet-wordpress-prod | gzip -1 | ssh "$target" 'gunzip | docker load'

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
  ssh "$target" 'bash /opt/pressfleet/infra/prod/up.sh --no-build'
else
  echo "Code and images are on the server. Next:"
  echo "  ssh $target 'bash /opt/pressfleet/infra/prod/init-env.sh <domain>'"
  echo "  ssh $target 'bash /opt/pressfleet/infra/prod/up.sh --no-build'"
fi
