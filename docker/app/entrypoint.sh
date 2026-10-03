#!/bin/sh
# |--------------------------------------------------------------------------
# | OJT Tracker container entrypoint
# |--------------------------------------------------------------------------
# |
# | Runs before every command the image serves (php-fpm, queue:listen,
# | schedule:work, and one-off `docker compose run --rm app ...` tasks):
# |
# |   1. seed the shared public/ volume from the image's pristine copy
# |      (Nginx serves these files; rebuilds re-seed on the next boot)
# |   2. wait for MySQL so `docker compose up` can start everything at once
# |   3. refresh the framework caches and fix permissions
# |
# | Migrations are deliberately NOT automatic — they run as an explicit
# | documented step (docker compose run --rm app php artisan migrate --force)
# | so the team stays in control of schema changes.

set -e

cd /var/www

# 1. public/ lives on a volume shared with the Nginx container.
mkdir -p /var/www/public
cp -a /opt/public-src/. /var/www/public/

# 2. Block until MySQL answers — Docker starts all services together.
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
    echo "Waiting for MySQL at $DB_HOST..."
    until php -r "
        try {
            new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306', getenv('DB_DATABASE')),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [PDO::ATTR_TIMEOUT => 3]
            );
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        sleep 2
    done
    echo "MySQL is up."
fi

# 3. Caches + permissions. The config cache is only safe once APP_KEY exists,
#    which keeps `run --rm app php artisan key:generate` usable on first boot.
if [ -n "$APP_KEY" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

php artisan storage:link >/dev/null 2>&1 || true
chown -R www-data:www-data storage bootstrap/cache public

exec "$@"
