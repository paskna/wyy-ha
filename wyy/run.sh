#!/usr/bin/with-contenv bashio
set -Eeuo pipefail

readonly APP_ROOT="/opt/wyy"
readonly DATA_ROOT="/data/wyy"
readonly STORAGE_ROOT="${DATA_ROOT}/storage"
readonly DATABASE_DIR="${DATA_ROOT}/database"
readonly DATABASE_FILE="${DATABASE_DIR}/wyy.sqlite"
readonly BACKUP_DIR="${DATA_ROOT}/backups"
readonly KEY_FILE="${DATA_ROOT}/app.key"
readonly RUNTIME_FILE="${STORAGE_ROOT}/app/config/runtime.php"
readonly LOCK_FILE="${STORAGE_ROOT}/app/installed.lock"

log() { bashio::log.info "WYY: $*"; }
fail() { bashio::log.fatal "WYY: $*"; exit 1; }

LOG_LEVEL="$(bashio::config 'log_level')"
TIMEZONE="$(bashio::config 'timezone')"
export TZ="${TIMEZONE:-Europe/Zurich}"
export WYY_DEPLOYMENT="homeassistant"
export APP_ENV="production"
export APP_DEBUG="false"
export DB_CONNECTION="sqlite"
export DB_DATABASE="${DATABASE_FILE}"
export DB_FOREIGN_KEYS="true"
export DB_BUSY_TIMEOUT="5000"
export DB_JOURNAL_MODE="WAL"
export CACHE_STORE="file"
export SESSION_DRIVER="file"
export SESSION_LIFETIME="120"
export SESSION_PATH="/"
export SESSION_SECURE_COOKIE="false"
export SESSION_HTTP_ONLY="true"
export SESSION_SAME_SITE="lax"
export QUEUE_CONNECTION="sync"
export FILESYSTEM_DISK="local"
export LOG_CHANNEL="stderr"
export LOG_LEVEL="${LOG_LEVEL}"

mkdir -p "${DATABASE_DIR}" "${BACKUP_DIR}" "${STORAGE_ROOT}/app/config" "${STORAGE_ROOT}/app/scans" \
    "${STORAGE_ROOT}/framework/cache" "${STORAGE_ROOT}/framework/sessions" \
    "${STORAGE_ROOT}/framework/views" "${STORAGE_ROOT}/logs" /run/nginx /run/php
touch "${DATABASE_FILE}"

if [[ ! -s "${KEY_FILE}" ]]; then
    umask 077
    php -r 'echo "base64:" . base64_encode(random_bytes(32));' > "${KEY_FILE}"
fi

export APP_KEY="$(tr -d '\r\n' < "${KEY_FILE}")"
[[ "${APP_KEY}" == base64:* ]] || fail "Der persistente APP_KEY ist ungueltig."

umask 077
cat > "${RUNTIME_FILE}" <<EOF
<?php

return [
    'APP_ENV' => 'production',
    'APP_KEY' => '${APP_KEY}',
    'APP_DEBUG' => 'false',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => '${DATABASE_FILE}',
    'DB_FOREIGN_KEYS' => 'true',
    'DB_BUSY_TIMEOUT' => '5000',
    'DB_JOURNAL_MODE' => 'WAL',
    'CACHE_STORE' => 'file',
    'SESSION_DRIVER' => 'file',
    'SESSION_PATH' => '/',
    'SESSION_HTTP_ONLY' => 'true',
    'SESSION_SAME_SITE' => 'lax',
    'QUEUE_CONNECTION' => 'sync',
    'FILESYSTEM_DISK' => 'local',
    'WYY_DEPLOYMENT' => 'homeassistant',
];
EOF

cd "${APP_ROOT}"
if [[ -s "${DATABASE_FILE}" ]] && php artisan migrate:status --no-interaction | grep -q 'Pending'; then
    backup_file="${BACKUP_DIR}/wyy-before-migration-$(date -u +%Y%m%dT%H%M%SZ).sqlite"
    cp "${DATABASE_FILE}" "${backup_file}"
    log "SQLite-Sicherung vor Migration erstellt: $(basename "${backup_file}")"

    # Keep the five latest pre-migration snapshots alongside Home Assistant backups.
    mapfile -t backups < <(ls -1t "${BACKUP_DIR}"/wyy-before-migration-*.sqlite 2>/dev/null || true)
    for ((index=5; index<${#backups[@]}; index++)); do
        rm -f "${backups[index]}"
    done
fi

log "Pruefe Datenbankstruktur und fuehre erforderliche Migrationen aus."
php artisan migrate --force --no-interaction || fail "Migration fehlgeschlagen. Details stehen im App-Log."

php -r '
$db = new PDO("sqlite:" . getenv("DB_DATABASE"));
$db->exec("PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;");
' || fail "SQLite konnte nicht mit WAL und Foreign Keys initialisiert werden."

printf '{"deployment":"homeassistant","completed_at":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "${LOCK_FILE}"
chmod 0600 "${KEY_FILE}" "${RUNTIME_FILE}" "${LOCK_FILE}"

rm -f /run/php/php-fpm.pid
php-fpm84 -F &
PHP_FPM_PID=$!

cleanup() {
    kill -TERM "${PHP_FPM_PID}" 2>/dev/null || true
    wait "${PHP_FPM_PID}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

log "Starte WYY auf dem Home-Assistant-Ingress-Port 8099."
nginx -g 'daemon off;' &
NGINX_PID=$!
wait "${NGINX_PID}"
