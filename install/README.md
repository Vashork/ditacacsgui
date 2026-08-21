# TacacsGUI Installer (fork)

## Status
> **FUNCTIONALLY COMPLETE (code) — NOT yet end-to-end tested on a real VM.** All 9 step scripts (`inc/func_*.sh`) and the orchestrator (`install.sh`) are implemented, plus all config templates under `inc/conf/`. `bash -n` is clean and a stubbed dry-run walks all 9 steps. **Remaining work:** vendor `install/tac_plus.tgz` and run the installer on a clean Ubuntu 22.04/24.04 VM (master + a slave) to prove the full flow incl. HA replication. Do NOT run on production until that VM test passes.

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
├── install.sh            # main orchestrator, idempotent, --role flag
├── inc/
│   ├── map.sh            # paths/constants
│   ├── func_os.sh        # apt, Angie, PHP 8.2 PPA, ntpsec
│   ├── func_db.sh        # MySQL 8.0, two DBs, tgui_user, repl user, login-paths
│   ├── func_ha.sh        # master/slave/standalone: binlog, server-id, CHANGE MASTER TO
│   ├── func_python.sh    # venv + pip deps
│   ├── func_app.sh       # git clone, composer install (lock), .env
│   ├── func_tacplus.sh   # build tac_plus 2024 + PCRE2, install systemd unit
│   ├── func_web.sh       # self-signed cert, Angie vhosts, PHP-FPM pool/ini, activate
│   ├── func_sudoers.sh   # www-data drop-in (visudo -cf validated)
│   ├── func_finalize.sh  # data/log dirs, health checks
│   └── conf/             # config templates
│       ├── angie/        # tacacsgui.conf, tacacsgui-ssl.conf
│       ├── systemd/      # tac_plus.service, tgui-selftest.{service,timer}
│       ├── mysql/        # tgui.cnf, replication.cnf
│       ├── php/          # tgui-fpm-pool.conf, 80-tacacsgui.ini
│       └── sudoers/      # www-data-sudo, tgui_sudoers
└── tac_plus.tgz          # (TODO) vendored 2024 build (from ichantio) — NOT yet committed
```

## Design decisions
- **Angie not Nginx**: drop-in Nginx-compatible fork; same vhost syntax.
- **MySQL 8.0 not SQLite**: HA/replication is mandatory; SQLite can't replicate and write-locks under AAA load. SQLite remains DEV-ONLY.
- **composer install (not update)**: uses the committed `web/api/composer.lock` for reproducible builds.
- **Phone-home disabled**: tacacsgui.com/updates is dead; code defaults to https://localhost/updates/.
- **Based on ichantio artifacts**: tac_plus.tgz (2024-09-11, PCRE2), mysql_config_editor pattern, ntpsec choice, parser filters — adapted, not copied.

## REMAINING (before this can run on a real box)
- **Vendor `install/tac_plus.tgz`** (download from ichantio, ~8.6 MB, PCRE2 2024-09-11 build). Not yet committed (binary).
- **End-to-end install test on a clean Ubuntu 22.04/24.04 VM** — master + a slave to prove HA replication (GTID, `Slave_IO/SQL_Running=Yes`).
- Confirm the distro's Angie service name (`angie` vs `nginx`) and the `angie` apt package availability on 22.04/24.04 during the VM test.

Everything else (orchestrator, all 9 `func_*.sh`, all config templates) is implemented.

## References
- ichantio installer: https://github.com/ichantio/tacacsgui-installation
- ichantio source (diff baseline): https://github.com/ichantio/tacacsgui
- upstream installer (being replaced): https://github.com/tacacsgui/tgui_install
