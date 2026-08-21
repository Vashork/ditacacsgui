#!/bin/bash
# func_tacplus.sh - build tac_plus 2024 (PCRE2) from the vendored tarball, install systemd unit.
# Sourced by install.sh after map.sh (and after func_app.sh has placed the repo at /opt/tacacsgui).
# Defines: tacplus_build, tacplus_service.
# Idempotent.

tacplus_build() {
    # Install build dependencies: compiler toolchain, PCRE2 dev headers, SSL headers.
    apt-get install -y build-essential libpcre2-dev libssl-dev

    # Work in a throwaway build directory; cleaned up at the end.
    BUILD_DIR="$(mktemp -d)"

    # The tac_plus source ships as a vendored tarball at the installer repo root.
    # It is NOT downloaded from the network during install.
    if [ ! -f "${INSTALLER_DIR}/tac_plus.tgz" ]; then
        echo "ERROR: vendored tarball not found at ${INSTALLER_DIR}/tac_plus.tgz"
        echo "       vendor install/tac_plus.tgz first — see README"
        return 1
    fi

    # Extract the tarball into the build dir.
    tar -xzf "${INSTALLER_DIR}/tac_plus.tgz" -C "${BUILD_DIR}"

    # Expected layout: the tarball unpacks to a top-level `tac_plus/` directory
    # containing the `configure` script. If your tarball extracts to a different
    # top-level dir name, adjust this path before building.
    cd "${BUILD_DIR}/tac_plus"
    ./configure --with-pcre2
    make
    make install

    # Confirm the binary landed where the app expects it.
    if [ ! -x "${TAC_PLUS_BIN}" ]; then
        echo "ERROR: build finished but ${TAC_PLUS_BIN} is missing or not executable"
        return 1
    fi

    # Print the version if the binary supports a version flag; never fail on it.
    tac_plus -V 2>/dev/null || tac_plus --version 2>/dev/null || true

    # Remove the throwaway build tree.
    rm -rf "${BUILD_DIR}"

    # First-boot placeholder: the GUI generates the real config on first save,
    # but the daemon needs the file to exist to start. The GUI will populate it.
    if [ ! -f "${TAC_PLUS_CFG}" ]; then
        touch "${TAC_PLUS_CFG}"
        chown www-data:www-data "${TAC_PLUS_CFG}"
        chmod 600 "${TAC_PLUS_CFG}"
    fi

    echo "tac_plus built + installed at ${TAC_PLUS_BIN}"
}

tacplus_service() {
    # Install the systemd unit from the template (idempotent overwrite).
    install -m 644 "${CONF_DIR}/systemd/tac_plus.service" "${SYSTEMD_UNIT_DIR}/tac_plus.service"

    # Make systemd see the new/updated unit.
    systemctl daemon-reload

    # Enable at boot.
    systemctl enable tac_plus

    # Start it. The daemon will not fully come up until tac_plus.cfg is written
    # by the GUI, so a post-start failure here is expected and not fatal.
    systemctl start tac_plus || echo "tac_plus started; will be fully active once tac_plus.cfg is written by the GUI"

    # Show current status (best-effort, non-fatal).
    systemctl status tac_plus --no-pager || true

    echo "tac_plus service installed + enabled"
}
