#!/bin/bash
# func_python.sh - Python venv (PEP 668 safe) + pip deps for the parser.
# Sourced by install.sh after map.sh. Defines: python_setup.
# Idempotent.

python_setup() {
    # Non-interactive apt (avoid prompts on 22.04/24.04).
    export DEBIAN_FRONTEND=noninteractive

    # python3 + venv + build deps for mysqlclient.
    # default-libmysqlclient-dev provides the MySQL client libs mysqlclient
    # requires on 22.04/24.04 (libmysqlclient-dev / python3-mysqldb are dead on 24.04).
    # pkg-config is REQUIRED: mysqlclient's setup.py shells out to
    # `pkg-config --exists mysqlclient|libmysqlclient` to locate the client lib;
    # without it the build fails with "Can not find valid pkg-config name".
    apt-get install -y python3 python3-venv python3-pip python3-dev \
        default-libmysqlclient-dev build-essential pkg-config

    # Create the venv (idempotent).
    local VENV_DIR="${TGUI_DATA}/venv"
    if [ ! -d "$VENV_DIR" ]; then
        python3 -m venv "$VENV_DIR"
    fi

    # Upgrade pip inside the venv.
    "${VENV_DIR}/bin/pip" install --upgrade pip

    # Parser / config-manager deps (pip is idempotent).
    "${VENV_DIR}/bin/pip" install sqlalchemy alembic mysqlclient pexpect pyyaml pyotp gitpython requests

    # Parser data dir (idempotent).
    mkdir -p "${TGUI_DATA_PARSER}"

    # Note for operators: use the venv python, not system python.
    echo "[python] venv at ${VENV_DIR}; the app should use ${VENV_DIR}/bin/python (not system python)"

    # Export venv path for later steps.
    export TGUI_VENV="$VENV_DIR"
}
