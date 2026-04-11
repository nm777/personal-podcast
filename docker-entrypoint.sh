#!/bin/sh
set -e

# Fix ownership (entrypoint runs as root)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database 2>/dev/null || true
echo "Fixing storage permissions..."

# Ensure YouTube temp directory exists
mkdir -p /var/www/html/storage/app/public/temp-youtube
chown -R www-data:www-data /var/www/html/storage/app/public/temp-youtube 2>/dev/null || true

# Install composer deps if missing (bind mount dev scenario)
if [ ! -d /var/www/html/vendor ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader 2>/dev/null || composer install --no-interaction
fi

# Ensure SQLite database file exists and is writable
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
fi

# Run migrations only when explicitly enabled
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running database migrations..."
    gosu www-data php artisan migrate --force
else
    echo "Skipping migrations (set RUN_MIGRATIONS=true to enable)"
fi

echo "Starting service..."
if [ "$1" = "php-fpm" ]; then
    # PHP-FPM master must run as root; pool config sets child user to www-data
    exec "$@"
else
    # Other commands (artisan queue:work, etc.) run as www-data
    exec gosu www-data "$@"
fi
