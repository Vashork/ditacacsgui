<?php

declare(strict_types=1);

namespace tgui;

use Illuminate\Database\Capsule\Manager as Capsule;
use tgui\Controllers\APIChecker\APIDatabase;

/**
 * Production helper: builds the full TacacsGUI schema in a MySQL database on
 * first run (when the DB is still empty).
 *
 * WHY THIS EXISTS
 * ---------------
 * The app used to rely on `APICheckerCtrl::getCheckDatabase` (GET /apicheck/
 * database/?update=1) to create tables + seed defaults. That endpoint is
 * auth-gated (it returns 401 when $_SESSION['error'] is set) and there is no
 * bootstrap token on a truly empty database, so a fresh install could never
 * self-initialize: the very first POST /auth/signin/ hit
 * `Table 'tgui.ldap_bind' doesn't exist` and 500'd.
 *
 * The SQLite dev path already had a first-run builder (`SchemaBuilderSqlite`),
 * but the MySQL branch in bootstrap/app.php had no equivalent. This class is
 * that equivalent: it is called from bootstrap/app.php the moment Capsule is
 * booted, BEFORE any auth middleware runs, and only when the schema is absent
 * (idempotent — it is a no-op on every subsequent request).
 *
 * The column-creation logic mirrors APICheckerCtrl::createTable (the same
 * APIDatabase::$tablesArr type hints, same Blueprint calls) so the resulting
 * MySQL schema is identical to what the GUI's "update database" flow would
 * have produced.
 */
class SchemaBuilderMysql
{
    /**
     * Create all tables + seed defaults in BOTH connections (default + logging)
     * when the schema is absent. Idempotent: if `api_users` already exists it
     * does nothing.
     */
    public static function buildIfMissing(): void
    {
        // Idempotency guard: only bootstrap a truly empty schema.
        // getAllTables() returns different shapes across Laravel/illuminate
        // versions: an array of strings, an array of arrays, or an array of
        // stdClass objects (each with a `name` property). Normalize all of them.
        $tables = Capsule::connection('default')->getSchemaBuilder()->getAllTables();
        $hasApiUsers = false;
        foreach ($tables as $t) {
            $name = null;
            if (is_string($t)) {
                $name = $t;
            } elseif (is_array($t)) {
                $name = $t['name'] ?? null;
            } elseif (is_object($t)) {
                $name = $t->name ?? (isset($t->{'Table'}) ? $t->{'Table'} : null);
            }
            if ($name === 'api_users') {
                $hasApiUsers = true;
                break;
            }
        }
        if ($hasApiUsers) {
            return; // schema already present
        }

        $apiDb = new APIDatabase();

        foreach ($apiDb->databases as $database) {
            $tablesArr = ($database === 'logging') ? $apiDb->tablesArr_log : $apiDb->tablesArr;
            foreach ($tablesArr as $tableName => $tableColumns) {
                $builder = Capsule::connection($database)->getSchemaBuilder();
                if ($builder->hasTable($tableName)) {
                    continue; // per-table idempotency (partial prior runs)
                }
                self::createTable($database, $tableName, $tableColumns);
                self::setDefaultValues($database, $tableName);
            }
        }
    }

    /**
     * Create one table from an APIDatabase column definition.
     * Mirrors APICheckerCtrl::createTable.
     */
    private static function createTable(string $database, string $tableName, array $tableColumns): void
    {
        $builder = Capsule::connection($database)->getSchemaBuilder();
        $builder->disableForeignKeyConstraints();
        $builder->create($tableName, function ($table) use ($tableColumns) {
            if (!@$tableColumns['unsetId']) {
                $table->increments('id');
            } else {
                unset($tableColumns['unsetId']);
            }
            $timestamp = (@$tableColumns['unsetTimestamp']) ? false : true;
            unset($tableColumns['unsetTimestamp']);

            foreach ($tableColumns as $columnName => $columnAttr) {
                switch ($columnAttr[0]) {
                    case 'string':
                        $columnObj = $table->string($columnName);
                        break;
                    case 'integer':
                        $columnObj = $table->integer($columnName);
                        break;
                    case 'text':
                        $columnObj = $table->text($columnName);
                        break;
                    case 'timestamp':
                        $columnObj = $table->timestamp($columnName);
                        break;
                    case 'foreign-null':
                        $columnObj = $table->integer($columnName)->nullable()->unsigned();
                        $table->foreign($columnName)
                            ->references($columnAttr[1]['references'])
                            ->on($columnAttr[1]['on'])
                            ->onDelete($columnAttr[1]['onDelete']);
                        break;
                    case 'foreign':
                        $columnObj = $table->unsignedInteger($columnName);
                        $table->foreign($columnName)
                            ->references($columnAttr[1]['references'])
                            ->on($columnAttr[1]['on'])
                            ->onDelete($columnAttr[1]['onDelete']);
                        break;
                    default:
                        $columnObj = $table->string($columnName);
                        break;
                }

                if (($columnAttr[0] === 'integer' && $columnAttr[1] !== '_')
                    || ($columnAttr[1] !== '_' && $columnAttr[0] === 'string')
                    || ($columnAttr[1] !== '_' && $columnAttr[0] === 'text')
                ) {
                    $columnObj->default($columnAttr[1]);
                } elseif ($columnAttr[0] !== 'foreign' && $columnAttr[0] !== 'foreign-null') {
                    $columnObj->nullable();
                }
            }

            if ($timestamp) {
                $table->timestamps();
            }
        });
    }

    /**
     * Seed the default rows for a freshly-created table.
     * Mirrors APICheckerCtrl::setDefaultValues.
     */
    private static function setDefaultValues(string $database, string $tableName): void
    {
        $conn  = Capsule::connection($database);
        $table = $conn->table($tableName);
        $now   = date('Y-m-d H:i:s', time());

        switch ($tableName) {
            case 'api_users':
                // Default admin: tacgui / tacgui (same as the GUI's own seed).
                $table->insert([
                    'username' => 'tacgui',
                    'password' => '$2y$10$zZPJVM/qGizgWqq1Mbs0T.E6uCG.fz09pWYxsYj2oieAhm2BZtUPe', // tacgui
                    'email'    => '',
                    'firstname' => 'tacgui',
                    'surname'  => 'tacgui',
                    'position' => '',
                    'group'    => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                break;
            case 'api_user_groups':
                $table->insert([
                    'name'         => 'Administrator',
                    'rights'       => 2,
                    'default_flag' => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                break;
            case 'api_settings':
                $table->insert([
                    'update_key' => self::generateRandomString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                break;
            case 'tac_global_settings':
                $table->insert([
                    'manual' => "log = accounting_log {\n"
                        . "\tdestination =  \"| " . (defined('TAC_ROOT_PATH') ? TAC_ROOT_PATH : '/opt/tacacsgui') . "/parser/tacacs_parser.sh accounting\" \n"
                        . "\tlog separator = \"|!|\"} \n"
                        . "log = authentication_log {\n"
                        . "\tdestination = \"| " . (defined('TAC_ROOT_PATH') ? TAC_ROOT_PATH : '/opt/tacacsgui') . "/parser/tacacs_parser.sh authentication\"\n"
                        . "\tlog separator = \"|!|\"}\n"
                        . "log = authorization_log {\n"
                        . "\tdestination = \"| " . (defined('TAC_ROOT_PATH') ? TAC_ROOT_PATH : '/opt/tacacsgui') . "/parser/tacacs_parser.sh authorization\"\n"
                        . "\tlog separator = \"|!|\"}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                break;
            case 'tac_users':
                $table->insert([
                    'username'   => 'admin',
                    'login'      => 'test',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                break;
            case 'tac_user_groups':
                $table->insert([
                    'name'       => 'defaultUserGroup',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                break;
            case 'tac_device_groups':
                $table->insert([
                    'name'           => 'defaultGroup',
                    'banner_welcome' => 'Unauthorized access is prohibited!',
                    'banner_failed'  => 'Go away! Unauthorized access is prohibited!',
                    'banner_motd'    => 'Today is a perfect day! Have a nice day!',
                    'default_flag'   => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                break;
            case 'api_password_policy':
            case 'api_smtp':
            case 'api_backup':
            case 'api_notification':
            case 'mavis_ldap':
            case 'mavis_otp':
            case 'mavis_sms':
            case 'mavis_local':
                $table->insert([
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                break;
            // tac_devices: intentionally no seed (matches the GUI's behavior).
            default:
                // No seed for this table.
                break;
        }
    }

    /** Random string for api_settings.update_key (mirrors APICheckerCtrl). */
    private static function generateRandomString(int $length = 32): string
    {
        return substr(bin2hex(random_bytes(32)), 0, $length);
    }
}
