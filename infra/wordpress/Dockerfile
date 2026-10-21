# The official WordPress image plus WP-CLI, so each site is managed with `docker compose exec siteN wp ...`
FROM wordpress:7.1.3-php8.4-apache

COPY --from=wordpress:cli-2.12.0-php8.4 /usr/local/bin/wp /usr/local/bin/wp
# wp db commands need a MySQL client
RUN apt-get update \
  && apt-get install -y --no-install-recommends mariadb-client \
  && rm -rf /var/lib/apt/lists/*
