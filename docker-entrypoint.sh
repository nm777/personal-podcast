#!/bin/bash

# Run database migrations
php artisan migrate --force

# Start PHP-FPM
php-fpm