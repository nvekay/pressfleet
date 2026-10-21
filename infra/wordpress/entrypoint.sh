#!/bin/sh
# The uploads volume is mounted inside the site before WordPress is copied in; it has to be writable by Apache
set -e
mkdir -p /var/www/html/wp-content/uploads
chown www-data:www-data /var/www/html/wp-content /var/www/html/wp-content/uploads
exec docker-entrypoint.sh "$@"
