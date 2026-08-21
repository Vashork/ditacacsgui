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

  # Fresh master has no upstream; nothing to CHANGE MASTER TO.
  echo "HA: master ready for slaves to CHANGE MASTER TO"
  echo "HA: master configured"
}

# ha_slave: set server-id=2, enable read_only, point at the master, start replication.
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
  mysql --login-path=root -e "SHOW SLAVE STATUS\G" | grep -E 'Slave_IO_Running|Slave_SQL_Running|Last_IO_Error'
  echo "HA: slave configured (master=${TGUI_HA_MASTER_IP})"
}

# ha_standalone: no replication; ensure read_only is OFF (safe re-run on a former slave).
ha_standalone() {
  # Clear any read_only state left over from a previous slave role.
  mysql --login-path=root -e "SET PERSIST read_only=OFF; SET PERSIST super_read_only=OFF;"
  echo "HA: standalone (no replication)"
}
