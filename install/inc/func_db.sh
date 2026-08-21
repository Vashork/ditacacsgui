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

    # Generate random 12-char alphanumeric passwords for app + replication users.
    TGUI_APP_PASS=$(openssl rand -base64 16 | tr -cd '[:alnum:]' | cut -c1-12)
    TGUI_REPL_PASS=$(openssl rand -base64 16 | tr -cd '[:alnum:]' | cut -c1-12)
    export TGUI_MYSQL_APPPASS="$TGUI_APP_PASS"
    export TGUI_MYSQL_REPLPASS="$TGUI_REPL_PASS"

    # NOTE: On a fresh Ubuntu MySQL 8, root uses auth_socket by default, so running
    # as the root shell user via the socket works without a password. Bootstrap the
    # schema/users here via `mysql -u root` (socket auth); we only set the root
    # password later if it is still unset (handled by the login-path step).
    #
    # Create both databases (tgui = replicated, tgui_log = master-only) if absent.
    # Create the app user (localhost only) and the replication user if absent.
    mysql -u root -e "
        CREATE DATABASE IF NOT EXISTS ${DB_APP} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE DATABASE IF NOT EXISTS ${DB_LOG} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${TGUI_APP_PASS}';
        GRANT ALL PRIVILEGES ON ${DB_APP}.* TO '${DB_USER}'@'localhost';
        GRANT ALL PRIVILEGES ON ${DB_LOG}.* TO '${DB_USER}'@'localhost';
        CREATE USER IF NOT EXISTS '${DB_REPL_USER}'@'%' IDENTIFIED BY '${TGUI_REPL_PASS}';
        GRANT REPLICATION SLAVE ON *.* TO '${DB_REPL_USER}'@'%';
        FLUSH PRIVILEGES;
    "

    echo "[tgui] DB provisioned."
}

db_loginpaths() {
    # Store mysql_config_editor login-paths so later steps (and the app) can use
    # `mysql --login-path=tacacsgui` / `mysql --login-path=root` without any
    # plaintext password on disk. Drives the interactive prompt with unbuffer+expect.

    # root login-path (uses the root password generated in db_install).
    sudo unbuffer expect -c "
    spawn mysql_config_editor set --login-path=root --host=localhost --user=root --password
    expect -nocase \"Enter password:\" {send \"${TGUI_MYSQL_ROOTPASS}\r\"; interact}
    " > /dev/null

    # app login-path for tgui_user (uses the app password from db_provision).
    sudo unbuffer expect -c "
    spawn mysql_config_editor set --login-path=tacacsgui --host=localhost --user=${DB_USER} --password
    expect -nocase \"Enter password:\" {send \"${TGUI_MYSQL_APPPASS}\r\"; interact}
    " > /dev/null

    echo "[tgui] login-paths stored."
}
