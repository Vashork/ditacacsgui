#!/bin/bash
# func_db.sh - MySQL 8.0: install, two DBs, app+repl users, config drop-ins, login-paths.
# Sourced by install.sh after map.sh. Defines: db_install, db_provision, db_loginpaths.
# Idempotent.

db_install() {
    export DEBIAN_FRONTEND=noninteractive

    # Always (re)generate the root password so db_loginpaths has a value even
    # when mysql is already installed (idempotent re-runs). Fresh installs set
    # it via the login-path step; re-runs just re-store the same login-path.
    MYSQL_ROOTPASS="${TGUI_MYSQL_ROOTPASS:-$(openssl rand -base64 16 | tr -cd '[:alnum:]' | cut -c1-12)}"
    export TGUI_MYSQL_ROOTPASS="$MYSQL_ROOTPASS"

    # Only install if not already present (idempotent guard).
    if ! command -v mysql >/dev/null 2>&1; then
        apt-get -y install mysql-server
        echo "[tgui] MySQL installed."
    fi

    # Ensure the service is enabled and running (idempotent).
    systemctl enable --now mysql
}

db_provision() {
    # Copy MySQL config drop-ins so tgui is replicated and tgui_log is master-only.
    install -m 644 ${CONF_DIR}/mysql/tgui.cnf /etc/mysql/mysql.conf.d/99-tgui.cnf
    install -m 644 ${CONF_DIR}/mysql/replication.cnf /etc/mysql/mysql.conf.d/98-tgui-replication.cnf

    # Restart to apply the drop-in configs.
    systemctl restart mysql

    # Passwords are persisted to /opt/tgui_data/ha/ so re-runs and master->slave
    # propagation can reuse them. The operator can also pre-export
    # TGUI_MYSQL_REPLPASS / TGUI_MYSQL_APPPASS to force a specific value
    # (e.g. the same repl password on both master and slave).
    #
    # Resolution order per password:
    #   1. Pre-exported env var (TGUI_MYSQL_APPPASS / TGUI_MYSQL_REPLPASS) wins.
    #   2. Else, a previously persisted file under /opt/tgui_data/ha/ is reused.
    #   3. Else, a new random 12-char alphanumeric password is generated and
    #      persisted so future re-runs reuse it.
    mkdir -p /opt/tgui_data/ha

    # --- App password (TGUI_MYSQL_APPPASS) ---
    if [ -z "${TGUI_MYSQL_APPPASS:-}" ]; then
        if [ -f /opt/tgui_data/ha/app-pass ]; then
            TGUI_MYSQL_APPPASS=$(cat /opt/tgui_data/ha/app-pass)
        else
            TGUI_MYSQL_APPPASS=$(openssl rand -base64 16 | tr -cd '[:alnum:]' | cut -c1-12)
            umask 077
            echo -n "$TGUI_MYSQL_APPPASS" > /opt/tgui_data/ha/app-pass
            chmod 600 /opt/tgui_data/ha/app-pass
        fi
    fi

    # --- Replication password (TGUI_MYSQL_REPLPASS) ---
    # On HA, master and slave MUST share this value; pre-exporting it (or
    # copying the persisted file) between the two nodes is what makes
    # CHANGE MASTER TO succeed.
    if [ -z "${TGUI_MYSQL_REPLPASS:-}" ]; then
        if [ -f /opt/tgui_data/ha/repl-pass ]; then
            TGUI_MYSQL_REPLPASS=$(cat /opt/tgui_data/ha/repl-pass)
        else
            TGUI_MYSQL_REPLPASS=$(openssl rand -base64 16 | tr -cd '[:alnum:]' | cut -c1-12)
            umask 077
            echo -n "$TGUI_MYSQL_REPLPASS" > /opt/tgui_data/ha/repl-pass
            chmod 600 /opt/tgui_data/ha/repl-pass
        fi
    fi

    export TGUI_MYSQL_APPPASS
    export TGUI_MYSQL_REPLPASS

    # NOTE: On a fresh Ubuntu MySQL 8, root uses auth_socket by default, so running
    # as the root shell user via the socket works without a password. Bootstrap the
    # schema/users here via `mysql -u root` (socket auth); we only set the root
    # password later if it is still unset (handled by the login-path step).
    #
    # Create both databases (tgui = replicated, tgui_log = master-only) if absent.
    # Create the app user (localhost only) and the replication user if absent.
    #
    # On re-runs, CREATE USER IF NOT EXISTS is a no-op (users already exist, so
    # the password is NOT changed) — that is correct: the persisted password
    # files under /opt/tgui_data/ha/ guarantee the value we reference here is
    # the SAME value the app's .env already has, so consistency is preserved.
    # --- Read-only app password (TGUI_MYSQL_ROPASS) ---------------------------
    # The slave web app connects to its DB as the read-only user tgui_ro
    # (bootstrap/app.php uses username=tgui_ro, password=psk_s where psk_s is
    # taken from ha-settings.yaml). That user + password must exist BEFORE the
    # web tier is served, otherwise the very first request on the slave 500s.
    # The GUI's HA-setup flow can later change psk_s; the installer creates a
    # working default now. Resolution order mirrors the app/repl passwords:
    #   1. Pre-exported TGUI_MYSQL_ROPASS wins.
    #   2. Else a persisted /opt/tgui_data/ha/ro-pass file is reused.
    #   3. Else a new random 12-char password is generated + persisted.
    #
    # NOTE: created on ALL roles (master/standalone too) — it is harmless there
    # (the app connects as tgui_user on non-slaves) and it means a node that is
    # later flipped to slave has tgui_ro already present.
    if [ -z "${TGUI_MYSQL_ROPASS:-}" ]; then
        if [ -f /opt/tgui_data/ha/ro-pass ]; then
            TGUI_MYSQL_ROPASS=$(cat /opt/tgui_data/ha/ro-pass)
        else
            TGUI_MYSQL_ROPASS=$(openssl rand -base64 16 | tr -cd '[:alnum:]' | cut -c1-12)
            umask 077
            echo -n "$TGUI_MYSQL_ROPASS" > /opt/tgui_data/ha/ro-pass
            chmod 600 /opt/tgui_data/ha/ro-pass
        fi
    fi
    export TGUI_MYSQL_ROPASS

    mysql -u root -e "
        CREATE DATABASE IF NOT EXISTS ${DB_APP} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE DATABASE IF NOT EXISTS ${DB_LOG} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${TGUI_MYSQL_APPPASS}';
        GRANT ALL PRIVILEGES ON ${DB_APP}.* TO '${DB_USER}'@'localhost';
        GRANT ALL PRIVILEGES ON ${DB_LOG}.* TO '${DB_USER}'@'localhost';
        CREATE USER IF NOT EXISTS '${DB_RO_USER}'@'localhost' IDENTIFIED BY '${TGUI_MYSQL_ROPASS}';
        GRANT SELECT ON ${DB_APP}.* TO '${DB_RO_USER}'@'localhost';
        GRANT SELECT ON ${DB_LOG}.* TO '${DB_RO_USER}'@'localhost';
        CREATE USER IF NOT EXISTS '${DB_REPL_USER}'@'%' IDENTIFIED BY '${TGUI_MYSQL_REPLPASS}';
        GRANT REPLICATION SLAVE ON *.* TO '${DB_REPL_USER}'@'%';
        FLUSH PRIVILEGES;
    "

    echo "[tgui] DB provisioned."
}

db_loginpaths() {
    # Store mysql_config_editor login-paths so later steps (and the app) can use
    # `mysql --login-path=tacacsgui` / `mysql --login-path=root` without any
    # plaintext password on the CLI.
    #
    # HOW: `mysql_config_editor set --password` normally opens an interactive
    # prompt. We AVOID driving that prompt with expect/unbuffer (it hangs when
    # there is no real TTY, e.g. under sudo/setsid in an unattended install).
    # Instead we pass the password through the MYSQL_PWD environment variable,
    # which mysql_config_editor reads non-interactively and then writes into the
    # obfuscated ~/.mylogin.cnf. No TTY, no expect, fully deterministic.
    #
    # MYSQL_PWD is deprecated by MySQL for general use but is exactly the
    # sanctioned non-interactive input channel for mysql_config_editor, and the
    # password is only held in a short-lived env var of a root subshell.

    # root login-path (uses the root password generated in db_install).
    MYSQL_PWD="${TGUI_MYSQL_ROOTPASS}" sudo mysql_config_editor set \
        --login-path=root --host=localhost --user=root --password

    # app login-path for tgui_user (uses the app password from db_provision).
    MYSQL_PWD="${TGUI_MYSQL_APPPASS}" sudo mysql_config_editor set \
        --login-path=tacacsgui --host=localhost --user=${DB_USER} --password

    echo "[tgui] login-paths stored."
}
