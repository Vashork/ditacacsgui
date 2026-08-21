#!/bin/bash
# func_sudoers.sh - install the www-data sudoers drop-in (validated).
# Sourced by install.sh after map.sh (and after func_app.sh so the scripts exist).
# Defines: sudoers_install.
# Idempotent.

sudoers_install() {
    # Write the www-data drop-in from the template (root:root, 440 so sudo can read it).
    # Idempotent: install -m overwrites an existing drop-in.
    install -m 440 "${CONF_DIR}/sudoers/www-data-sudo" "${SUDOERS_DIR}/tacacsgui-www-data"

    # Validate the drop-in BEFORE it can cause issues.
    # visudo -cf checks syntax WITHOUT activating; on failure we delete so a bad file never persists.
    # If a malformed sudoers file were left in place, it could lock out root.
    if ! visudo -cf "${SUDOERS_DIR}/tacacsgui-www-data"; then
        # Validation failed: remove the bad drop-in so sudo never parses it.
        rm -f "${SUDOERS_DIR}/tacacsgui-www-data"
        echo "ERROR: sudoers validation failed; drop-in removed, no change applied"
        return 1
    fi

    # Success: drop-in is present and syntactically valid.
    echo "sudoers installed + validated: ${SUDOERS_DIR}/tacacsgui-www-data"
}
