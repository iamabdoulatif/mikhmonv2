#!/bin/sh
# =============================================================================
# Mikhmonv2 Entrypoint for MikroTik full image (nginx + php-fpm)
# =============================================================================

# Ensure data directory exists (for Docker volume persistence)
mkdir -p /var/www/data
chown -R nobody:nogroup /var/www/data /tmp/php_sessions

# Start PHP-FPM in background
php-fpm81 -D

# Wait for PHP-FPM socket
for i in $(seq 1 10); do
    if [ -S /run/nginx/php-fpm.sock ]; then
        break
    fi
    sleep 0.2
done

# Start nginx in foreground (keeps container alive)
exec nginx -g 'daemon off;'
