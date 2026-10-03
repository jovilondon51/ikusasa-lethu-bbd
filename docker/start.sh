#!/bin/sh
set -eu
port="${PORT:-8080}"
case "$port" in ''|*[!0-9]*) echo 'PORT must be numeric.' >&2; exit 1;; esac
if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then echo 'PORT is out of range.' >&2; exit 1; fi
sed -i "s/Listen 80$/Listen $port/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$port>/" /etc/apache2/sites-available/000-default.conf
mkdir -p "$UPLOAD_ROOT" "$UPLOAD_ROOT/sessions"
chown www-data:www-data "$UPLOAD_ROOT" "$UPLOAD_ROOT/sessions"
chmod 700 "$UPLOAD_ROOT" "$UPLOAD_ROOT/sessions"
printf 'session.save_path="%s"\n' "$UPLOAD_ROOT/sessions" > /usr/local/etc/php/conf.d/sessions.ini
# This checks the existing database. It does not create tables or modify Aiven.
attempt=0
until php /var/www/html/scripts/check-ready.php; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 15 ]; then echo 'Database/storage readiness check failed.' >&2; exit 1; fi
    sleep 2
done
exec apache2-foreground
