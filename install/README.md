# TacacsGUI Installer (fork)

## Status
> **SKELETON / IN PROGRESS.** The config templates (Angie vhosts, systemd units, PHP-FPM pool, MySQL 8.0 + replication, sudoers) are in place. The main `install.sh` orchestration and the `inc/func_*.sh` step scripts are still being written. Do NOT run this installer yet.

## What it installs
Target stack (locked):
- **OS**: Ubuntu 22.04 / 24.04 LTS
- **PHP**: 8.2 (ondrej PPA) + PHP-FPM
- **Web server**: **Angie** (Nginx-compatible) serving the Slim 4 app
- **DB**: **MySQL 8.0** — `tgui` (replicated) + `tgui_log` (master-only)
- **HA**: Master/Slave via MySQL replication (GTID, ROW binlog) — **required**
- **tac_plus**: 2024 build (PCRE2) — vendored `tac_plus.tgz`
- **Python**: venv-based (PEP 668 safe) for the log parser
- **NTP**: ntpsec
- **Service manager**: native systemd (replaces dead upstart)

## HA roles
`install.sh --role master|slave|standalone`
- master: binlog on, GTID, serves auth + logs
- slave: read_only, CHANGE MASTER TO <master> with tgui_repl user, serves auth (logs replicated from master)
- standalone: no replication

## Directory layout
```
install/
├── README.md             # exists
├── install.sh            # (TODO) main orchestrator, idempotent, --role flag
├── inc/
│   ├── map.sh            # (TODO) paths/constants
│   ├── func_os.sh        # (TODO) apt, Angie, PHP 8.2 PPA, ntpsec
│   ├── func_db.sh        # (TODO) MySQL 8.0, two DBs, tgui_user, repl user
│   ├── func_python.sh    # (TODO) venv + pip deps
│   ├── func_app.sh       # (TODO) git clone, composer install (lock), config
│   ├── func_tacplus.sh   # (TODO) build tac_plus 2024 + PCRE2, install unit
│   ├── func_ha.sh        # (TODO) master/slave: binlog, server-id, CHANGE MASTER TO
│   └── conf/             # EXISTS — config templates
│       ├── angie/        # EXISTS: tacacsgui.conf, tacacsgui-ssl.conf
│       ├── systemd/      # EXISTS: tac_plus.service, tgui-selftest.{service,timer}
│       ├── mysql/        # EXISTS: tgui.cnf, replication.cnf
│       ├── php/          # EXISTS: tgui-fpm-pool.conf, 80-tacacsgui.ini
│       └── sudoers/      # EXISTS: www-data-sudo, tgui_sudoers
└── tac_plus.tgz          # (TODO) vendored 2024 build (from ichantio)
```

## Design decisions
- **Angie not Nginx**: drop-in Nginx-compatible fork; same vhost syntax.
- **MySQL 8.0 not SQLite**: HA/replication is mandatory; SQLite can't replicate and write-locks under AAA load. SQLite remains DEV-ONLY.
- **composer install (not update)**: uses the committed `web/api/composer.lock` for reproducible builds.
- **Phone-home disabled**: tacacsgui.com/updates is dead; code defaults to https://localhost/updates/.
- **Based on ichantio artifacts**: tac_plus.tgz (2024-09-11, PCRE2), mysql_config_editor pattern, ntpsec choice, parser filters — adapted, not copied.

## NOT YET (do not expect these to work)
- install.sh orchestration
- inc/func_*.sh step scripts
- tac_plus.tgz vendoring
- end-to-end install test on a clean VM

## References
- ichantio installer: https://github.com/ichantio/tacacsgui-installation
- ichantio source (diff baseline): https://github.com/ichantio/tacacsgui
- upstream installer (being replaced): https://github.com/tacacsgui/tgui_install
