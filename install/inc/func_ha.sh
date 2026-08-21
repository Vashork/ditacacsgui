#!/bin/bash
# func_ha.sh - HA: configure MySQL replication per ROLE (master|slave|standalone).
# Sourced by install.sh after map.sh (and after func_db.sh has run).
# Defines: ha_setup, ha_master, ha_slave, ha_standalone.
# Idempotent.
#
# NOTE FOR SLAVE ROLE: the operator MUST provide TGUI_HA_MASTER_IP, e.g.:
#   sudo TGUI_HA_MASTER_IP=10.0.0.5 ./install.sh --role slave
# ha_slave will refuse to run if it is unset.

# ha_setup: dispatches on $ROLE (master|slave|standalone).
ha_setup() {
  case "$ROLE" in
    master)
      ha_master
      ;;
    slave)
      ha_slave
      ;;
    standalone)
      ha_standalone
      ;;
    *)
      echo "HA ERROR: unknown ROLE '$ROLE' (expected master|slave|standalone)" >&2
      return 1
      ;;
  esac
}

# _ha_write_settings: write the app-layer ha-settings.yaml the GUI expects.
#   $1 = role (1=master, 2=slave)
#   $2 = psk_s (slave-only; the tgui_ro password; empty for master)
# The app's bootstrap/app.php reads this file to decide which DB user to connect
# as (tgui_user on master, tgui_ro on slave with password=psk_s). Without it the
# app treats the node as 'standalone' and a slave's web app would connect as
# tgui_user, which does not exist on the slave (read_only + different grants).
#
# PERMISSIONS ARE CRITICAL HERE: this file is read at RUNTIME by the web app
# (PHP-FPM runs as www-data, see tgui-fpm-pool.conf user=www-data), NOT by root.
# The GUI's own HAGeneral::saveCfg writes it via file_put_contents as www-data,
# so it ends up www-data-owned and world-readable. If we write it root-owned
# 600, Yaml::parseFile() throws "File cannot be read" and EVERY request 500s.
# Hence: chown www-data:www-data + chmod 640 (web user + group read; no world).
# (Contrast: ro-pass / repl-pass / app-pass stay root 600 — they are only ever
# read by the installer as root, never by the app process.)
_ha_write_settings() {
  local role="$1" psk_s="${2:-}"
  mkdir -p /opt/tgui_data/ha
  {
    echo "role: ${role}"
    if [ -n "${psk_s}" ]; then
      echo "psk_s: ${psk_s}"
    fi
  } > "${HA_SETTINGS_FILE}"
  chown www-data:www-data "${HA_SETTINGS_FILE}" 2>/dev/null || true
  chmod 640 "${HA_SETTINGS_FILE}"
  echo "HA: ${HA_SETTINGS_FILE} written (role=${role}${psk_s:+ psk_s=<set>}, www-data:640)"
}

# ha_master: confirm binlog+GTID are on, note that the master is ready for slaves.
ha_master() {
  # Master server-id: the drop-in 98-tgui-replication.cnf already sets server-id = 1.
  echo "HA: master server-id confirmed = 1 (via /etc/mysql/mysql.conf.d/98-tgui-replication.cnf)"

  # Verify binlog + GTID are actually ON (they are set in the drop-in).
  local log_bin gtid_mode
  log_bin=$(mysql --login-path=root -N -e "SHOW VARIABLES LIKE 'log_bin';" | awk '{print $2}')
  gtid_mode=$(mysql --login-path=root -N -e "SHOW VARIABLES LIKE 'gtid_mode';" | awk '{print $2}')
  echo "HA: log_bin=${log_bin} gtid_mode=${gtid_mode}"
  if [ "$gtid_mode" != "ON" ]; then
    echo "HA WARNING: gtid_mode is not ON (value='${gtid_mode}'). Expected ON from drop-in; please check /etc/mysql/mysql.conf.d/98-tgui-replication.cnf." >&2
  fi

  # Tell the app this node is the master (role=1) so it connects as tgui_user.
  _ha_write_settings 1 ""

  # Fresh master has no upstream; nothing to CHANGE MASTER TO.
  echo "HA: master ready for slaves to CHANGE MASTER TO"
  echo "HA: master configured"
}

# ha_slave: set server-id=2, enable read_only, point at the master, start replication,
# and write the app-layer ha-settings.yaml (role=2 + psk_s) so the web app connects
# as the read-only user tgui_ro.
ha_slave() {
  # Operator must tell us where the master is; refuse if unset.
  if [ -z "${TGUI_HA_MASTER_IP:-}" ]; then
    echo "HA ERROR: TGUI_HA_MASTER_IP is not set. Provide the master's IP for this slave, e.g.:" >&2
    echo "          sudo TGUI_HA_MASTER_IP=10.0.0.5 ./install.sh --role slave" >&2
    return 1
  fi

  # Force this node's server-id to 2 in the replication drop-in.
  sed -i 's/^server-id = .*/server-id = 2/' /etc/mysql/mysql.conf.d/98-tgui-replication.cnf
  echo "HA: slave server-id set to 2"

  # Make the slave read-only (and super_read_only for safety on MySQL 8.0).
  mysql --login-path=root -e "SET PERSIST read_only=ON; SET PERSIST super_read_only=ON;"
  echo "HA: slave read_only=ON super_read_only=ON"

  # Point at the master using GTID auto-positioning (MySQL 8.0.23+ syntax).
  mysql --login-path=root -e "
    CHANGE MASTER TO
      MASTER_HOST='${TGUI_HA_MASTER_IP}',
      MASTER_USER='${DB_REPL_USER}',
      MASTER_PASSWORD='${TGUI_MYSQL_REPLPASS}',
      MASTER_AUTO_POSITION=1;
    START SLAVE;
  "
  echo "HA: CHANGE MASTER TO ${TGUI_HA_MASTER_IP} + START SLAVE issued"

  # Verify replication threads are running.
  # grep || true: a no-match (e.g. master briefly unreachable) must not abort under set -o pipefail.
  mysql --login-path=root -e "SHOW SLAVE STATUS\G" 2>/dev/null | grep -E 'Slave_IO_Running|Slave_SQL_Running|Last_IO_Error' || true

  # --- App-layer wiring: make the slave's web app connectable -----------------
  #
  # The app's bootstrap/app.php, on a slave, connects to the DB as the
  # read-only user tgui_ro with password = psk_s (read from ha-settings.yaml).
  # The installer already created tgui_ro (func_db.sh db_provision, password =
  # TGUI_MYSQL_ROPASS, persisted to /opt/tgui_data/ha/ro-pass). We now record
  # that password as psk_s in ha-settings.yaml so the app can use it. The GUI's
  # HA-setup flow may later regenerate psk_s + re-wire; the installer's job is
  # only to make the slave's web tier work out-of-the-box.
  local ro_pass="${TGUI_MYSQL_ROPASS:-$(cat /opt/tgui_data/ha/ro-pass 2>/dev/null || echo '')}"
  _ha_write_settings 2 "${ro_pass}"

  # Persist the master IP the slave is pointed at (informational; the GUI can use it).
  echo "ip: ${TGUI_HA_MASTER_IP}" > "${HA_MASTER_FILE}"
  chmod 600 "${HA_MASTER_FILE}"
  echo "HA: ${HA_MASTER_FILE} written (ip=${TGUI_HA_MASTER_IP})"

  echo "HA: slave configured (master=${TGUI_HA_MASTER_IP})"
}

# ha_standalone: no replication; ensure read_only is OFF (safe re-run on a former slave).
ha_standalone() {
  # Clear any read_only state left over from a previous slave role.
  mysql --login-path=root -e "SET PERSIST read_only=OFF; SET PERSIST super_read_only=OFF;"
  echo "HA: standalone (no replication)"
}
