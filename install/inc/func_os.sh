#!/bin/bash
# func_os.sh - OS provisioning: base packages, Angie, PHP 8.2 (ondrej PPA), ntpsec.
# Sourced by install.sh after map.sh. Defines: os_preflight, os_install.
# Idempotent: every step checks current state before acting.

os_preflight() {
    # Verify running as Ubuntu 22.04 or 24.04 (accept 20.04 with a warning).
    local id="" version_id=""
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        id="${ID:-}"
        version_id="${VERSION_ID:-}"
    fi

    if [ "$id" != "ubuntu" ]; then
        echo "WARNING: expected Ubuntu (ID=ubuntu) but detected ID='${id:-unknown}'. Aborting." >&2
        return 1
    fi

    case "$version_id" in
        22.04|24.04)
            echo "Detected OS: Ubuntu ${version_id}"
            ;;
        20.04)
            echo "Detected OS: Ubuntu ${version_id}"
            echo "WARNING: Ubuntu 20.04 is not a primary target (22.04/24.04). Proceeding anyway."
            return 0
            ;;
        *)
            echo "WARNING: expected Ubuntu 22.04 or 24.04 but detected Ubuntu ${version_id:-unknown}. Aborting." >&2
            return 1
            ;;
    esac

    # Verify we are running as root.
    if [ "$(id -u)" -ne 0 ]; then
        echo "ERROR: this installer must be run as root (current EUID=$(id -u))." >&2
        return 1
    fi

    return 0
}

os_install() {
    # Non-interactive front-end for all debconf/apt prompts.
    export DEBIAN_FRONTEND=noninteractive

    # Refresh package indexes.
    apt-get update -y

    # Base packages required by the installer and the app.
    # NOTE: software-properties-common is the 22.04/24.04 name (python-software-properties is dead).
    apt-get install -y software-properties-common curl unzip tar net-tools expect ntpsec ca-certificates

    # Add the ondrej PPA for PHP 8.2 (idempotent-ish: safe to run when already present).
    if command -v add-apt-repository >/dev/null 2>&1; then
        add-apt-repository -y ppa:ondrej/php
    else
        echo "WARNING: add-apt-repository not found; cannot add ondrej PPA." >&2
        return 1
    fi

    # Re-index after adding the PPA.
    apt-get update -y

    # PHP 8.2 + PHP-FPM + required extensions.
    # (sockets ships with PHP core — no separate package needed.)
    apt-get install -y php8.2 php8.2-common php8.2-cli php8.2-fpm \
        php8.2-curl php8.2-ldap php8.2-gd php8.2-mbstring \
        php8.2-zip php8.2-mysql php8.2-xml

    # Web server: Angie (Nginx-compatible fork).
    # The apt package is 'angie' across the supported releases; a post-install
    # 'angie -v' check should confirm it is present and runnable.

    # Angie is NOT in default Ubuntu repos; add the vendor apt repo + GPG key first.
    # Without this, 'apt-get install angie' fails on a stock VM with
    # "Unable to locate package angie".
    #
    # 1) GPG key: skip re-download if it already exists (idempotent).
    if [ ! -f /etc/apt/trusted.gpg.d/angie-signing.gpg ]; then
        curl -fsSL -o /etc/apt/trusted.gpg.d/angie-signing.gpg https://angie.software/keys/angie-signing.gpg
    fi

    # 2) apt repo: skip re-echo if the list file already exists (idempotent).
    if [ ! -f /etc/apt/sources.list.d/angie.list ]; then
        local codename
        codename=$(. /etc/os-release && echo "$VERSION_CODENAME")
        echo "deb https://download.angie.software/angie/ubuntu/ ${codename} main" > /etc/apt/sources.list.d/angie.list
    fi

    # 3) Re-index so apt picks up the new repo before we install from it.
    apt-get update -y

    # 4) Now the angie package resolves from the vendor repo.
    apt-get install -y angie

    # ntpsec log directory (the ntpsec package itself is installed above).
    mkdir -p /var/log/ntpsec
    chown root:ntpsec /var/log/ntpsec
    chmod 775 /var/log/ntpsec

    # Enable + start OS-level services.
    # Angie is intentionally NOT started here: the web step activates it
    # after the vhosts are in place.
    systemctl enable --now ntpsec
    systemctl enable "php${PHP_VERSION}-fpm"

    echo "OS layer done"
}
