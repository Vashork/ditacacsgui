#!/bin/bash
# func_app.sh - deploy the app: get the code, composer install (lock), generate .env.
# Sourced by install.sh after map.sh (and after func_db.sh / func_python.sh).
# Defines: app_deploy, app_compose, app_env.
# Idempotent.

APP_REPO="${APP_REPO:-https://github.com/Vashork/ditacacsgui.git}"

# app_deploy: ensure composer, get the code (clone or reuse), hand ownership to the web user.
app_deploy() {
    # Ensure composer is available; on a fully-offline box the operator should
    # pre-place /usr/local/bin/composer instead.
    if ! command -v composer >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi

    # Get the code: reuse an existing checkout, otherwise clone from the repo.
    if [ -f "${TGUI_API_DIR}/composer.json" ]; then
        echo "app code already present, skipping clone"
    else
        git clone "${APP_REPO}" "${TGUI_ROOT}"
        echo "cloned ${APP_REPO} -> ${TGUI_ROOT}"
    fi

    # The web user owns the app tree.
    chown -R www-data:www-data "${TGUI_ROOT}"

    echo "app deployed to ${TGUI_ROOT}"
}

# app_compose: vendor dependencies reproducibly from the committed lock file.
app_compose() {
    # composer install (NOT update): the repo ships a committed composer.lock,
    # so install resolves exactly those pinned versions. Run as www-data so the
    # generated vendor/ tree has correct ownership for the web server.
    sudo -u www-data composer install -d "${TGUI_API_DIR}" --no-interaction --no-progress

    # Verify vendor was actually produced.
    if [ ! -d "${TGUI_API_DIR}/vendor" ]; then
        echo "ERROR: composer install did not produce ${TGUI_API_DIR}/vendor" >&2
        return 1
    fi

    echo "composer install done (vendor present)"
}

# app_env: make web/api/.env production-ready (idempotent) and set credentials.
app_env() {
    local created=0

    # Create .env from the template only if it is absent.
    #
    # IMPORTANT: the repo SHIPS a committed web/api/.env (a local-dev file with
    # DB_DRIVER=sqlite and placeholder DB_PASSWORD). If we only seds a file we
    # created, that shipped dev .env would be left untouched and the app would
    # try to use SQLite + a placeholder password -> the whole web tier fails.
    # Therefore we ALWAYS apply the production credential overrides below,
    # whether or not we created the file this run. This is the installer's job:
    # turn the app's config into a working production (MySQL/HA) one.
    if [ ! -f "${TGUI_API_DIR}/.env" ]; then
        cp "${TGUI_API_DIR}/.env.example" "${TGUI_API_DIR}/.env"
        created=1
        echo ".env created from .env.example"
    else
        echo ".env already present; applying production overrides to it"
    fi

    # Always (re)apply the production values. JWT_SECRET is only regenerated
    # when we created the file (so re-runs don't invalidate existing tokens);
    # DB creds / driver / URL are idempotent and safe to re-assert every run.
    local jwt_secret
    if [ "${created}" -eq 1 ]; then
        jwt_secret=$(openssl rand -hex 32)
        sed -i "s|^JWT_SECRET=.*|JWT_SECRET=${jwt_secret}|" "${TGUI_API_DIR}/.env"
    fi

    # Force MySQL (never sqlite) for production/HA.
    sed -i "s|^DB_DRIVER=.*|DB_DRIVER=mysql|" "${TGUI_API_DIR}/.env"
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" "${TGUI_API_DIR}/.env"
    sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST:-localhost}|" "${TGUI_API_DIR}/.env"
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_APP}|" "${TGUI_API_DIR}/.env"
    sed -i "s|^DB_DATABASE_LOG=.*|DB_DATABASE_LOG=${DB_LOG}|" "${TGUI_API_DIR}/.env"
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "${TGUI_API_DIR}/.env"
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${TGUI_MYSQL_APPPASS}|" "${TGUI_API_DIR}/.env"
    sed -i "s|^APP_URL=.*|APP_URL=https://${WEBSERVER_NAME}|" "${TGUI_API_DIR}/.env"

    # Restrict .env to the web user + group.
    chown www-data:www-data "${TGUI_API_DIR}/.env"
    chmod 640 "${TGUI_API_DIR}/.env"

    echo ".env ready at ${TGUI_API_DIR}/.env (DB_DRIVER=mysql, user=${DB_USER})"
}
