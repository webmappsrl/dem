#!/bin/bash
set -e

echo "Deployment started ..."

# Enter maintenance mode or return true
# if already is in maintenance mode
(php artisan down) || true


# Install composer dependencies
composer install  --no-interaction --prefer-dist --optimize-autoloader
# php artisan nova:install

# Run database migrations
php artisan migrate --force

# Clear the old cache
php artisan clear-compiled

composer dump-autoload
php artisan config:clear
php artisan optimize

# Exit maintenance mode
php artisan up

echo "Deployment finished!"
