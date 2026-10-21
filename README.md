# PressFleet

Publish to a network of WordPress sites from one hub. Posts come in from a CSV file or a Google Sheet, a queue
publishes them to every site with retries and no duplicates, and each site runs a connector plugin with a signed
REST API, WP-CLI commands and a lead form block. The demo sites share one block theme in three brands.

> Work in progress. Done so far: three local WordPress 7.1 sites in Docker and the connector plugin's publishing API
> (`wp/plugins/pressfleet-connector`), with WordPress Coding Standards, PHPStan level 8 and integration tests.

## Run locally

Requires Node 22+, pnpm and Docker. PHP tooling runs in Docker too.

```bash
pnpm install
cp .env.example .env            # set WP_ADMIN_PASSWORD
pnpm dev:wp                     # three sites on http://localhost:8081, 8082 and 8083
pnpm php composer install       # PHP tooling for the plugin
pnpm php composer lint          # WordPress Coding Standards
pnpm php composer analyse       # PHPStan
pnpm --filter @pressfleet/connector env:start
pnpm --filter @pressfleet/connector test:php
```
