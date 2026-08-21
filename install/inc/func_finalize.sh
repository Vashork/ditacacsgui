#!/bin/bash
# func_finalize.sh - finalization: data/log dirs, ownership, health checks.
# Sourced by install.sh after map.sh (runs last, after all other steps).
# Defines: finalize_dirs, finalize_health.
# Idempotent.

# finalize_dirs: create the /opt/tgui_data skeleton + log dir with sane perms.
finalize_dirs() {
    # Data subdirectories (backups, HA, config-manager, parser).
    local d
    for d in \
        "${TGUI_DATA}" \
        "${TGUI_DATA_SSL}" \
        "${TGUI_DATA_BACKUP}" \
        "${TGUI_DATA_HA}" \
        "${TGUI_DATA_CONFMANAGER}" \
        "${TGUI_DATA_PARSER}"
    do
        mkdir -p "${d}"
    done

    # Log directory for the app + PHP-FPM error log.
    mkdir -p "${TGUI_LOG_DIR}"

    # The web user owns the app-writable data + log trees.
    chown -R www-data:www-data "${TGUI_DATA}" "${TGUI_LOG_DIR}" 2>/dev/null || true
    chmod 750 "${TGUI_LOG_DIR}" 2>/dev/null || true

    echo "data + log directories ready under ${TGUI_DATA} and ${TGUI_LOG_DIR}"
}

# finalize_health: best-effort smoke checks (non-fatal; report, don't abort).
finalize_health() {
    echo "---- health checks ----"

    # PHP-FPM up?
    if systemctl is-active --quiet "php${PHP_VERSION}-fpm"; then
        echo "[ok] php${PHP_VERSION}-fpm is active"
    else
        echo "[warn] php${PHP_VERSION}-fpm is NOT active (check: systemctl status php${PHP_VERSION}-fpm)"
    fi

    # Angie up?
    if systemctl is-active --quiet angie; then
        echo "[ok] angie is active"
    else
        echo "[warn] angie is NOT active (check: systemctl status angie)"
    fi

    # MySQL up + reachable via the app login-path?
    if mysql --login-path=tacacsgui -N -e "SELECT 1;" >/dev/null 2>&1; then
        echo "[ok] MySQL reachable via login-path 'tacacsgui'"
    else
        echo "[warn] MySQL NOT reachable via login-path 'tacacsgui' (check: mysql --login-path=tacacsgui -e 'SELECT 1;')"
    fi

    # tac_plus binary present?
    if [ -x "${TAC_PLUS_BIN}" ]; then
        echo "[ok] tac_plus binary present at ${TAC_PLUS_BIN}"
    else
        echo "[warn] tac_plus binary missing at ${TAC_PLUS_BIN} (GUI config will fail until built)"
    fi

    # App .env present?
    if [ -f "${TGUI_API_DIR}/.env" ]; then
        echo "[ok] app .env present at ${TGUI_API_DIR}/.env"
    else
        echo "[warn] app .env missing at ${TGUI_API_DIR}/.env"
    fi

    echo "---- first login ----"
    echo "Open https://${WEBSERVER_NAME}/ in a browser."
    echo "Default credentials come from the app's DB seed (see the project README / seed step)."
    echo "If HA role=slave, replication should show Slave_IO/SQL_Running=Yes (SHOW SLAVE STATUS)."
    echo "---- install complete ----"
}
