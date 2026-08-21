#!/bin/bash
# func_web.sh - web tier: self-signed cert, Angie vhosts, PHP-FPM pool/ini, activate.
# Sourced by install.sh after map.sh (and after func_app.sh has placed the code).
# Defines: web_ssl, web_angie, web_fpm, web_activate.
# Idempotent.

web_ssl() {
    # Ensure the SSL data directory exists.
    mkdir -p "${TGUI_DATA_SSL}"

    if [ "${WEBSERVER_SELFSIGNED_CERT}" = "1" ]; then
        # Generate a self-signed cert/key if absent.
        if [ ! -f "${TGUI_DATA_SSL}/${WEBSERVER_NAME}.cer" ] || [ ! -f "${TGUI_DATA_SSL}/${WEBSERVER_NAME}.key" ]; then
            openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
              -keyout "${TGUI_DATA_SSL}/${WEBSERVER_NAME}.key" \
              -out "${TGUI_DATA_SSL}/${WEBSERVER_NAME}.cer" \
              -subj "/CN=${WEBSERVER_NAME}"
        fi

        # The vhost template uses the fixed filenames tacacsgui.local.cer / .key;
        # map the generated cert onto those fixed names. The operator can later
        # replace these with a real CA-issued cert.
        cp -f "${TGUI_DATA_SSL}/${WEBSERVER_NAME}.cer" "${TGUI_DATA_SSL}/tacacsgui.local.cer"
        cp -f "${TGUI_DATA_SSL}/${WEBSERVER_NAME}.key" "${TGUI_DATA_SSL}/tacacsgui.local.key"

        chmod 600 "${TGUI_DATA_SSL}"/*.key 2>/dev/null || true

        echo "SSL ready (self-signed) for ${WEBSERVER_NAME}"
    else
        echo "NOTE: self-signed cert disabled; place a real cert/key at:"
        echo "  ${TGUI_DATA_SSL}/tacacsgui.local.cer"
        echo "  ${TGUI_DATA_SSL}/tacacsgui.local.key"
        echo "before activating the web server."
        return 0
    fi
}

web_angie() {
    # Ensure the Angie sites directory exists.
    mkdir -p "${ANGIE_SITES_DIR}"

    # Install both vhost templates (HTTP :80 and HTTPS :443), overwriting idempotently.
    install -m 644 "${CONF_DIR}/angie/tacacsgui.conf" "${ANGIE_SITES_DIR}/tacacsgui.conf"
    install -m 644 "${CONF_DIR}/angie/tacacsgui-ssl.conf" "${ANGIE_SITES_DIR}/tacacsgui-ssl.conf"

    # Sed the real server_name into both vhosts (replacing the placeholder).
    sed -i "s/server_name .*;/server_name ${WEBSERVER_NAME};/" \
        "${ANGIE_SITES_DIR}/tacacsgui.conf" \
        "${ANGIE_SITES_DIR}/tacacsgui-ssl.conf"

    echo "Angie vhosts installed for ${WEBSERVER_NAME}"
}

web_fpm() {
    # Install the PHP-FPM pool and ini overrides (idempotent overwrite).
    install -m 644 "${CONF_DIR}/php/tgui-fpm-pool.conf" "${FPM_POOL_DEST}"
    install -m 644 "${CONF_DIR}/php/80-tacacsgui.ini" "${FPM_INI_DEST}"

    # Restart PHP-FPM so it picks up the new pool/ini.
    systemctl restart "php${PHP_VERSION}-fpm"

    echo "PHP-FPM pool + ini installed"
}

web_activate() {
    # Validate the Angie config before touching the service.
    if ! angie -t; then
        echo "ERROR: angie config test failed; not reloading a broken config."
        return 1
    fi

    # Enable + start the web server. Note: the Angie package's service is named
    # `angie`; if your distro ships it as `nginx`, substitute that name.
    systemctl enable --now angie

    # Reload to pick up the new vhosts (fall back to restart if reload unsupported).
    systemctl reload angie || systemctl restart angie

    echo "Angie activated; app reachable at https://${WEBSERVER_NAME}"
}
