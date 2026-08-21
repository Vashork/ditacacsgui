<?php

declare(strict_types=1);

#########################################
########TACACSGUI API###################
define('APIVER', '1.0.0');
define('APIREVISION', 1);
define('TACVER', trim(preg_replace('/^tac_plus\s+version\s+/i', '', shell_exec('tac_plus -v 2>&1') ?? '')));
#########################################
#########################################

#########################################
########DATABASE (from .env)############
define('DB_NAME',        $_ENV['DB_DATABASE']     ?? 'tgui');
define('DB_NAME_LOG',    $_ENV['DB_DATABASE_LOG'] ?? 'tgui_log');
define('DB_USER',        $_ENV['DB_USERNAME']     ?? 'tgui_user');
define('DB_PASSWORD',    $_ENV['DB_PASSWORD']     ?? '');
define('DB_HOST',        $_ENV['DB_HOST']         ?? 'localhost');
define('DB_CHARSET',     $_ENV['DB_CHARSET']      ?? 'utf8mb4');
define('DB_COLLATE',     $_ENV['DB_COLLATE']      ?? 'utf8mb4_unicode_ci');
#########################################

#########################################
########TACACS+ PATHS (from .env)########
define('TAC_ROOT_PATH',    $_ENV['TAC_ROOT_PATH']     ?? '/opt/tacacsgui');
define('MAINSCRIPT',       TAC_ROOT_PATH . '/main.sh');
define('TAC_PLUS_CFG',     TAC_ROOT_PATH . '/tac_plus.cfg');
define('TAC_PLUS_CFG_TEST',TAC_ROOT_PATH . '/tac_plus.cfg_test');
define('TAC_PLUS_PARSING', TAC_ROOT_PATH . '/tacTestOutput.txt');
define('TAC_DEAMON',       TAC_ROOT_PATH . '/tac_plus.sh');
define('BACKUP_PATH',      TAC_ROOT_PATH . '/backup/database/');
#########################################

#########################################
########TACACSGUI HIGH AVAILABILITY#####
define('HASETTINGS', $_ENV['HA_SETTINGS_PATH'] ?? '/opt/tgui_data/ha/ha-settings.yaml');
define('HAMASTER',   $_ENV['HA_MASTER_PATH']   ?? '/opt/tgui_data/ha/ha-master.yaml');
define('HASLAVES',   $_ENV['HA_SLAVES_PATH']   ?? '/opt/tgui_data/ha/ha-slaves.yaml');
#########################################
