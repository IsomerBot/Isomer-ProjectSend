#!/usr/bin/env bash
set -euo pipefail

# Ensure runtime dirs exist & are writable
mkdir -p /var/www/html/upload/files /var/www/html/upload/temp /var/www/html/temp/php-sessions
chown -R www-data:www-data /var/www/html/upload /var/www/html/temp || true

# Start the main CMD
exec "$@"
