#!/bin/bash
# =============================================================================
# TacacsGUI installer - path / constant map
# Sourced by install.sh and every inc/func_*.sh. Do not execute directly.
# =============================================================================

# --- Roles (set by install.sh from --role before sourcing funcs) ------------
# ROLE=master|slave|standalone

# --- Install-time inputs (overridable via conf/install_params.conf) ---------
WEBSERVER_NAME="${WEBSERVER_NAME:-tacacsgui.local}"
WEBSERVER_SELFSIGNED_CERT="${WEBSERVER_SELFSIGNED_CERT:-1}"   # 1=self-signed, 0=CSR

# --- Absolute paths on the target host --------------------------------------
TGUI_ROOT="/opt/tacacsgui"
TGUI_DATA="/opt/tgui_data"
TGUI_WEB_ROOT="${TGUI_ROOT}/web"
TGUI_API_DIR="${TGUI_ROOT}/web/api"

TAC_PLUS_BIN="/usr/local/sbin/tac_plus"
TAC_PLUS_CFG="${TGUI_ROOT}/tac_plus.cfg"
TAC_PLUS_PID="/var/run/tac_plus.pid"

PHP_VERSION="8.2"
PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

# --- Data subdirectories (created by installer) -----------------------------
TGUI_DATA_SSL="${TGUI_DATA}/ssl"
TGUI_DATA_BACKUP="${TGUI_DATA}/backup/database"
TGUI_DATA_HA="${TGUI_DATA}/ha"
TGUI_DATA_CONFMANAGER="${TGUI_DATA}/confManager"
TGUI_DATA_PARSER="${TGUI_DATA}/parser"
TGUI_LOG_DIR="/var/log/tacacsgui"

# --- Databases ----------------------------------------------------------------
DB_APP="tgui"          # replicated (master -> slave)
DB_LOG="tgui_log"      # master-only, NOT replicated
DB_USER="tgui_user"
DB_REPL_USER="tgui_repl"

# --- MySQL replication --------------------------------------------------------
# server-id: master=1, slave=2 (installer may override per node)
[ -z "${MYSQL_SERVER_ID:-}" ] && MYSQL_SERVER_ID=1

# --- This installer's own layout (source tree) --------------------------------
INSTALLER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF_DIR="${INSTALLER_DIR}/inc/conf"

# --- PHP-FPM / Angie / systemd drop-in destinations --------------------------
FPM_POOL_DEST="/etc/php/${PHP_VERSION}/fpm/pool.d/tgui.conf"
FPM_INI_DEST="/etc/php/${PHP_VERSION}/fpm/conf.d/80-tacacsgui.ini"
ANGIE_SITES_DIR="/etc/angie/conf.d"
MYSQL_CONF_DIR="/etc/mysql/mysql.conf.d"
SUDOERS_DIR="/etc/sudoers.d"
SYSTEMD_UNIT_DIR="/etc/systemd/system"
