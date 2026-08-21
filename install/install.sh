#!/bin/bash
# =============================================================================
# TacacsGUI installer (fork) - MAIN ORCHESTRATOR
#
# SKELETON. Config templates under inc/conf/ are ready; the step functions in
# inc/func_*.sh are still being written. Do NOT run this on a production box
# until every func_*.sh is implemented and the whole flow is tested on a clean
# Ubuntu 22.04/24.04 VM.
#
# Usage:
#   sudo ./install.sh --role master|slave|standalone
#   sudo ./install.sh --role master --web-name tacacsgui.local
#
# Idempotent: safe to re-run; each step checks current state before acting.
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=inc/map.sh
source "${SCRIPT_DIR}/inc/map.sh"

# --- Defaults ----------------------------------------------------------------
ROLE="standalone"

# --- Parse args ---------------------------------------------------------------
while [[ $# -gt 0 ]]; do
  case "$1" in
    --role)      ROLE="$2"; shift 2 ;;
    --web-name)  WEBSERVER_NAME="$2"; shift 2 ;;
    -h|--help)
      grep '^#' "$0" | sed 's/^# \{0,1\}//'
      exit 0 ;;
    *) echo "Unknown arg: $1" >&2; exit 2 ;;
  esac
done

# --- Validate role -------------------------------------------------------------
case "$ROLE" in
  master|slave|standalone) ;;
  *) echo "ERROR: --role must be master|slave|standalone (got: $ROLE)" >&2; exit 2 ;;
esac

# --- Must run as root ----------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
  echo "ERROR: run as root (sudo)." >&2
  exit 1
fi

# --- Source step libraries (TODO: implement each) -----------------------------
# Each func_*.sh defines shell functions; install.sh calls them in order below.
# They are guarded so a missing file fails loudly (set -e) rather than silently.
for f in "${SCRIPT_DIR}"/inc/func_*.sh; do
  [[ -f "$f" ]] && source "$f"
done

log() { printf '\n\033[1;32m== %s ==\033[0m\n' "$*"; }

# --- Step functions (implement in inc/func_*.sh) --------------------------------
# The body of each is a TODO stub so the skeleton is runnable and shows the
# intended order. Replace the stub bodies with real implementations.
step_os()        { log "OS: preflight + apt, Angie, PHP ${PHP_VERSION} PPA, ntpsec"; os_preflight; os_install; }
step_db()        { log "DB: MySQL 8.0, ${DB_APP}+${DB_LOG}, ${DB_USER}, repl";   db_install; db_provision; db_loginpaths; }
step_ha()        { log "HA: role=${ROLE} (binlog, server-id, CHANGE MASTER TO)"; ha_setup; }
step_python()    { log "Python: venv + pip deps (parser)";                        python_setup; }
step_app()       { log "App: git clone, composer install (lock), config";         echo "TODO: func_app.sh"; }
step_tacplus()   { log "tac_plus: build 2024 + PCRE2, install systemd unit";      echo "TODO: func_tacplus.sh"; }
step_web()       { log "Web: Angie vhost + PHP-FPM pool/ini, activate";           echo "TODO: func_web.sh"; }
step_sudoers()   { log "Sudoers: www-data -> tac_plus.sh / main.sh";               echo "TODO: func_sudoers.sh"; }
step_finalize()  { log "Finalize: dirs, log perms, first boot, health check";      echo "TODO: func_finalize.sh"; }

# --- Run in order ---------------------------------------------------------------
log "TacacsGUI installer starting (role=${ROLE}, host=${WEBSERVER_NAME})"
step_os
step_db
step_ha
step_python
step_app
step_tacplus
step_web
step_sudoers
step_finalize

log "DONE (skeleton). Real step bodies are TODO."
