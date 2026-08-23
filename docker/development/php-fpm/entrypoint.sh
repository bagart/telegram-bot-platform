#!/bin/sh
set -e

cd /var/www

# Auto-setup: detect local libs, configure path repos if present.
# Skipped when vendor/ is already prepared (bind-mounted dev checkout):
# the image does not ship dev PHP extensions required by composer.json.
if [ ! -d "vendor" ] && [ -f "cmd/setup" ]; then
    echo "Running BAGArt setup..."
    ./cmd/setup --docker 2>/dev/null || composer install --no-interaction --no-progress
fi

# Clear configurations to avoid caching issues in development
echo "Clearing configurations..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run the default command (e.g., php-fpm or bash)
exec "$@"
