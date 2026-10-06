#!/bin/sh
set -eu

# One-off commands (for example, creating an admin) do not start the web server.
if [ "$1" = "apache2-foreground" ]; then
    : "${APP_KEY:?Set a persistent Laravel APP_KEY in the hosting environment.}"
    # Render supplies its public URL automatically; an explicit custom URL wins.
    export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-}}"
    : "${APP_URL:?Set APP_URL to the public HTTPS address when RENDER_EXTERNAL_URL is unavailable.}"

    export PORT="${PORT:-10000}"
    case "$PORT" in
        ''|*[!0-9]*) echo 'PORT must be a number.' >&2; exit 1 ;;
    esac
    if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
        echo 'PORT must be between 1 and 65535.' >&2
        exit 1
    fi

    if [ -n "${MYSQL_ATTR_SSL_CA:-}" ] && [ ! -r "$MYSQL_ATTR_SSL_CA" ]; then
        echo 'MYSQL_ATTR_SSL_CA must point to a readable CA certificate.' >&2
        exit 1
    fi

    printf 'Listen 0.0.0.0:%s\n' "$PORT" > /etc/apache2/ports.conf

    # Cache only at runtime, after Render injects environment variables/secrets.
    su -s /bin/sh www-data -c 'php artisan config:cache --no-interaction'
    su -s /bin/sh www-data -c 'php artisan route:cache --no-interaction'
    su -s /bin/sh www-data -c 'php artisan view:cache --no-interaction'

    # Free Render services have no pre-deploy shell. Migrations are opt-in.
    # Never reset the database or seed accounts during startup.
    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        su -s /bin/sh www-data -c 'php artisan migrate --force --no-interaction'
    fi
fi

exec docker-php-entrypoint "$@"
