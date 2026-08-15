#!/bin/sh
# =============================================================================
# Entrypoint — wires Render's $PORT into nginx's listen directive.
# =============================================================================
# We can't `ENV PORT` into nginx's `listen` line directly because nginx's
# config parser doesn't expand shell vars. The Dockerfile uses ${PORT} as a
# placeholder; this script replaces it with the actual value before exec.
# Defaults to 10000 (Render's default) if $PORT is unset for any reason.
# =============================================================================

set -eu

PORT_VALUE="${PORT:-10000}"

# Substitute ${PORT} -> $PORT_VALUE in nginx.conf
CONF="/etc/nginx/nginx.conf"
if [ -f "$CONF" ]; then
    # Use a delimiter that's unlikely to appear in the config ('|')
    sed -i "s|\${PORT}|${PORT_VALUE}|g" "$CONF"
    echo "[entrypoint] nginx will listen on 0.0.0.0:${PORT_VALUE}"
fi

# Make sure Laravel's writable dirs are owned by www-data
# (volumes on Render are empty on first boot, so storage/ & bootstrap/cache/
# may have wrong ownership from the COPY in the Dockerfile)
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

# Run database migrations on every boot. `artisan migrate --force` is
# idempotent — already-applied migrations are skipped. Set RUN_MIGRATIONS=0
# in Render's env if you ever need to boot without touching the DB
# (e.g. during incident response).
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    echo "[entrypoint] running database migrations"
    php artisan migrate --force --no-interaction || {
        echo "[entrypoint] migration failed; continuing to start web server anyway"
    }
fi

exec "$@"
