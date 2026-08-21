# TacacsGUI (ditacacsgui) — Управление

Практическое руководство: как заходить в веб-интерфейс, добавлять коммутаторы,
подключать LDAP, настраивать HA. Всё управление — **через веб-морду** (Angular SPA +
PHP API). Никаких отдельных CLI-интерфейсов нет; CLI — только для сервисного доступа
(`main.sh`, `tac_plus.sh`, `mysql`).

> Код развёрнут в `/opt/tacacsgui`, данные в `/opt/tgui_data`, конфиг tac_plus в
> `/opt/tacacsgui/tac_plus.cfg`. Web-сервер — **Angie** (:80, после установки — :443
> c TLS), backend — **PHP 8.2-FPM**, БД — **MySQL 8.0**.

---

## 1. Веб-интерфейс: куда заходить и первый вход

| Что | Значение |
|---|---|
| URL (из коробки) | `http://<ip-сервера>/` (или `http://tacacsgui.local/` после установки) |
| HTTPS (после установки) | `https://<ip-сервера>/` (self-signed cert при установке; замените на реальный) |
| Первый логин (GUI) | **`tacgui` / `tacgui`** |
| Первый вход TACACS (устройство) | **`admin` / `test`** |

- При первом обращении к **свежей** MySQL-БД приложение **само** создаёт схему
  (37 таблиц `tgui` + 7 `tgui_log`) и сидит дефолтного админа `tacgui`. Это делает
  `SchemaBuilderMysql::buildIfMissing()` в `bootstrap/app.php` — **до** auth,
  идемпотентно, на slave — пропускается (таблицы приходят реплицированием).
- **Смените пароль `tacgui` сразу после первого входа** (右上角 → сменить пароль;
  endpoint `POST /auth/signin/changePassword/`).
- Логин возвращает **JWT** (HS256, 1 час); дальше все API-запросы идут с токеном.

### Структура меню (по разделам API)
- **Users / User Groups** — пользователи GUI и их права (`/user/*`, `/user/group/*`).
- **TACACS → Devices** — коммутаторы/шлюзы (`/tacacs/device/*`).
- **TACACS → Device Groups** — группы устройств (`/tacacs/device/group/*`).
- **TACACS → Users** — TACACS+ пользователи (`/tacacs/user/*`).
- **TACACS → User Groups** — группы TACACS-пользователей (в т.ч. LDAP-группы) (`/tacacs/user/group/*`).
- **TACACS → ACL / Services / CMD** — списки команд, сервисы (shell/junos-exec и т.д.) (`/tacacs/acl/*`, `/tacacs/service/*`, `/obj/cmd/*`).
- **TACACS → Configuration** — генератор конфига tac_plus, применение, статус демона (`/tacacs/config/*`).
- **TACACS → Reports** — accounting/authentication/authorization, top-access, статус демона (`/tacacs/reports/*`).
- **Settings** — парольная политика, SMTP, время, сетевые интерфейсы, **HA** (`/settings/*`).
- **MAVIS** — бэкенды аутентификации: **LDAP**, OTP, SMS, Local (`/mavis/*`).
- **Logging** — журналы AAA (`/logging/*`).
- **Backup / Import-Export** — бэкап БД, экспорт/импорт конфига (`/backup/*`, `/export/*`, `/import/*`).
- **ConfManager** — менеджер конфигов устройств (diff, cron, credentials) (`/confmanager/*`).

---

## 2. Как заводить новые устройства (коммутаторы)

**Меню: TACACS → Devices → Add** (API: `POST /tacacs/device/add/`).

Поля (модель `tac_devices`):

| Поле | Что это |
|---|---|
| `name` | Имя устройства (уникальное) |
| `address` | IP коммутатора (с которого приходят TACACS+ запросы) |
| `key` | **Shared secret** — общий ключ TACACS+ между коммутатором и tac_plus. **Обязателен и должен совпадать** с `key` на самом устройстве. |
| `group` | Группа устройств (Device Group) |
| `vendor` / `model` / `type` | Производитель/модель/тип (влияет на генерацию конфига: cisco, junos, h3c, huawei, fortinet, paloalto, extreme, silverpeak…) |
| `acl` | Список разрешённых команд (ACL) |
| `user_group` | Группа TACACS-пользователей для этого устройства |
| `banner_welcome` / `banner_motd` / `banner_failed` | Баннеры (приветствие / message-of-the-day / при отказе) |
| `connection_timeout` | Таймаут сессии |
| `enable` / `enable_flag` / `disabled` | Флаги активации |
| `manual` | Ручной фрагмент конфига (дополнение автогенерации) |

**После добавления:**
1. Нажмите **Generate Config** (`/tacacs/config/generate/`) — tac_plus.cfg пересобирается.
2. **Apply / Daemon reload** (`/tacacs/config/apply/`) — tac_plus перезагружает конфиг.
3. На самом коммутаторе укажите tac_plus как TACACS+ сервер + **тот же `key`**.

> **Важно:** без совпадающего `key` устройство не сможет аутентифицироваться.
> Конфиг генерируется в `/opt/tacacsgui/tac_plus.cfg` (0600, www-data).

### Проверка, что устройство живо
`GET /tacacs/device/ping/` — ping устройства. Статус демона: `GET /tacacs/reports/daemon/status/`.

---

## 3. Как подключить LDAP

**Меню: MAVIS → LDAP** (API: `POST /mavis/ldap/`, модель `mavis_ldap`, id=1).

Поля (валидируются в `MAVISLDAPCtrl::postLDAPParams`):

| Поле | Что это |
|---|---|
| `hosts` | Хост(ы) LDAP-сервера (можно несколько) |
| `user` | **Bind DN** — DN для подключения (например `cn=admin,dc=example,dc=com`) |
| `password` | Пароль bind-пользователя |
| `path` | **Base DN** — корень поиска (например `dc=example,dc=com`) |
| `enabled` | `1`/`0` — включить LDAP как бэкенд аутентификации |
| `password_hide` | Скрывать пароль в GUI |

**Поддерживаются** ActiveDirectory и OpenLDAP (через `adldap2/adldap2` в vendor).

**После заполнения:**
1. **Test** (`POST /mavis/ldap/test/`) — проверка подключения к LDAP-серверу.
2. **Check** (`POST /mavis/ldap/check/`) — проверка аутентификации тестовым пользователем.
3. **Group search / bind** (`POST /mavis/ldap/group/search/`, `POST /mavis/ldap/group/bind/`) —
   поиск LDAP-групп и привязка их к TACACS-группам пользователей
   (таблицы `ldap_bind` / `ldap_groups`). Это нужно, чтобы **LDAP-группа → права TACACS**.
4. Применить конфиг tac_plus (генерация + reload, как в §2).

> **Цепочка при входе:** устройство → tac_plus → MAVIS → LDAP (bind + search) →
> проверка группы → разрешение команд. Логи — в Reports (authentication).

### Локальная аутентификация (альтернатива/дополнение)
Если LDAP не нужен или как fallback — **MAVIS → Local** (`/mavis/local/`):
TACACS-пользователи хранятся в БД (таблица `tac_users`, сид `admin`/`test`).
Также **OTP** (`/mavis/otp/`, генерация секретов + QR) и **SMS** (`/mavis/sms/`).

---

## 4. High Availability (master/slave)

HA — **MySQL GTID-репликация** `tgui` (мастер → слейв). `tgui_log` **не** реплицируется
(логи живут на мастере). Два слоя:

### Слой 1 — инсталлятор (база, работает из коробки)
- **Мастер**: `install.sh --role master` → `server-id=1`, binlog+GTID, `ha-settings.yaml: role: 1`,
  БД-юзер приложения = `tgui_user`.
- **Слейв**: `install.sh --role slave` + `TGUI_HA_MASTER_IP=<ip-мастера>` → `server-id=2`,
  `read_only=ON`/`super_read_only=ON`, `CHANGE MASTER TO ... MASTER_AUTO_POSITION=1`,
  `ha-settings.yaml: role: 2` + `psk_s` (пароль read-only юзера `tgui_ro`),
  **web-приложение на слейве** подключается к БД как **`tgui_ro`** (SELECT-only).
- **Обязательно:** пароли `TGUI_MYSQL_APPPASS` (tgui_user) и `TGUI_MYSQL_REPLPASS`
  (tgui_repl) **одинаковы на мастере и слейве** (иначе `CHANGE MASTER TO` не пройдёт).
- Проверка: на слейве `mysql --login-path=root -e "SHOW REPLICA STATUS\G"` →
  `Replica_IO_Running: Yes`, `Replica_SQL_Running: Yes`, `Seconds_Behind_Source: 0`.

### Слой 2 — GUI (опционально, поверх)
**Меню: Settings → HA** (`/settings/ha/`, `/ha/*`). Через веб-морду можно:
- назначить роль (master/slave), задать master-IP, `psk_s`;
- приложение само создаёт `tgui_ro`, пишет `ha-settings.yaml`, пере-настраивает репликацию
  (`main.sh ha tgui_ro <rootpw> <psk_s>` + `main.sh ha replication ...`).
- статус: `POST /settings/ha/status/`; удаление слейва: `POST /settings/ha/slave/del/`.

> Для теста «записал на мастере → увидел на слейве» достаточно слоя 1 (инсталлятора):
> записать строку на мастере, проверить на слейве через `tgui_user`/root.
> Веб-приложение на слейве читает реплицированные данные как `tgui_ro`.

---

## 5. Пользователи и права (GUI)

- **Добавить пользователя GUI**: `POST /user/add/` (имя, email, группа).
- **Группы + права**: `POST /user/group/rights/` — права задаются маской (rights=2 = админ).
- **Парольная политика**: `POST /settings/pwpolicy/` (длина, спецсимволы, цифры, совпадения).
- **SMTP** (для смены пароля по email): `POST /settings/smtp/` + `POST /settings/smtp/test/`.
- **Смена пароля**: `POST /auth/signin/changePassword/` / `POST /tacacs/user/change_passwd/change/`.

---

## 6. Эксплуатация (сервисные команды)

| Задача | Команда (на сервере, root) |
|---|---|
| Статус сервисов | `systemctl status angie php8.2-fpm mysql tac_plus` |
| Проверка конфига Angie | `angie -t` |
| Логи приложения | `tail -f /var/log/tacacsgui/*.log` |
| Логи FPM/Angie | `/var/log/php8.2-fpm.log`, `/var/log/angie/error.log` |
| Логи MySQL | `tail -f /var/log/mysql/error.log` |
| Репликация (слейв) | `mysql --login-path=root -e "SHOW REPLICA STATUS\G"` |
| Бэкап БД (через GUI) | меню **Backup** → Make / Download (`/backup/make/`) |
| Конфиг tac_plus | `cat /opt/tacacsgui/tac_plus.cfg` |
| Переустановка (идемпотентно) | `cd /opt/tacacsgui/install && sudo ./install.sh --role master` |

### Ключевые файлы
| Путь | Что |
|---|---|
| `/opt/tacacsgui/web` | web-корень (Angular SPA + `index.php` front controller) |
| `/opt/tacacsgui/web/api/.env` | креды приложения (DB_DRIVER, DB_*, JWT_SECRET) — **www-data:640** |
| `/opt/tacacsgui/tac_plus.cfg` | конфиг tac_plus (генерируется из GUI) — **0600** |
| `/opt/tgui_data/ha/ha-settings.yaml` | роль + psk_s (читает **приложение**, www-data:640) |
| `/opt/tgui_data/ha/{app,repl,ro}-pass` | пароли БД (root:600, только инсталлятор) |
| `/etc/angie/http.d/tacacsgui.conf` | vhost Angie |
| `/etc/php/8.2/fpm/pool.d/tgui.conf` | пул FPM (dedicated socket `php8.2-tgui.sock`) |

---

## 7. Частые проблемы

| Симптом | Причина / решение |
|---|---|
| Логин 500 `Table 'tgui.X' doesn't exist` | Схема не создана — убедиться, что `SchemaBuilderMysql` вызывается в `bootstrap/app.php` (MySQL-ветка, после `bootEloquent`). На slave — проверить, что репликация донесла таблицы с мастера. |
| Логин 500 `Yaml ... cannot be read` | `/opt/tgui_data/ha/ha-settings.yaml` с правами root:600 → должно быть **www-data:640** (приложение читает его как www-data). |
| 502 от Angie | FPM-сокет не поднят / неправильная группа. Проверить `php8.2-fpm` active, сокет `/run/php/php8.2-tgui.sock` существует, `listen.group=angie` в пуле. |
| Устройство не аутентифицируется | `key` на коммутаторе ≠ `key` в GUI. Сверить + перегенерировать/перезагрузить tac_plus. |
| LDAP-вход не работает | Проверить Test/Check в MAVIS→LDAP, bind DN, base DN (`path`), и что LDAP-группа привязана к TACACS-группе (bind). |
| Слейв не реплицирует | `SHOW REPLICA STATUS`: `Last_IO_Error` — чаще всего несовпадающий `TGUI_MYSQL_REPLPASS` или мастер недоступен по `MASTER_HOST`. |
| `tgui_ro` «Access denied» на слейве | `psk_s` в `ha-settings.yaml` ≠ пароль `tgui_ro`. Синхронизировать. |

---

*Руководство основано на актуальном коде ditacacsgui (commit 9aef261). Поля и маршруты —
из `web/api/app/routes.php`, `app/Models/*.php`, `app/Controllers/*`. Креды/IP — см.
Karpaty Wiki `credentials-and-ips.md`.*
