<?php

declare(strict_types=1);

namespace tgui;

use Illuminate\Database\Capsule\Manager as Capsule;
use tgui\Controllers\APIChecker\APIDatabase;

/**
 * DEV-ONLY helper: builds the full TacacsGUI schema in a SQLite database,
 * reverse-engineered from APIDatabase::$tablesArr / $tablesArr_log.
 *
 * This is used ONLY when DB_DRIVER=sqlite (local smoke-testing without MySQL).
 * Production always uses MySQL.
 */
class SchemaBuilderSqlite
{
    /** Map APIDatabase type hints to SQLite column types. */
    private static function sqliteType(array $col): string
    {
        $base = strtolower((string) $col[0]);
        return match (true) {
            $base === 'integer'  => 'INTEGER',
            $base === 'timestamp'=> 'TEXT',        // SQLite has no native timestamp; store as TEXT
            $base === 'text'     => 'TEXT',
            $base === 'foreign', $base === 'foreign-null' => 'INTEGER',
            default              => 'TEXT',        // string, anything else
        };
    }

    /** Build CREATE TABLE for one table definition. */
    private static function createTableSql(string $table, array $columns, bool $unsetId, bool $unsetTimestamp): string
    {
        $parts = [];
        if (!$unsetId) {
            $parts[] = '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        foreach ($columns as $name => $def) {
            // 'unsetId'/'unsetTimestamp' are flags, not columns
            if ($name === 'unsetId' || $name === 'unsetTimestamp') {
                continue;
            }
            $type = self::sqliteType($def);
            $parts[] = '"' . $name . '" ' . $type;
        }

        if (!$unsetTimestamp) {
            $parts[] = '"created_at" TEXT';
            $parts[] = '"updated_at" TEXT';
        }

        return 'CREATE TABLE IF NOT EXISTS "' . $table . '" (' . implode(', ', $parts) . ');';
    }

    /**
     * Create all tables + seed a default admin user (id=1, group=1, rights=2=admin).
     * @param string $sqliteFile path to the sqlite db file
     */
    public static function build(string $sqliteFile): void
    {
        $apiDb = new APIDatabase();

        // Default (main) database
        $pdoDefault = Capsule::connection('default')->getPdo();
        $pdoDefault->exec('PRAGMA foreign_keys = OFF;');
        foreach ($apiDb->tablesArr as $table => $columns) {
            $unsetId        = !empty($columns['unsetId']);
            $unsetTimestamp = !empty($columns['unsetTimestamp']);
            $pdoDefault->exec(self::createTableSql($table, $columns, $unsetId, $unsetTimestamp));
        }

        // Logging database
        $pdoLog = Capsule::connection('logging')->getPdo();
        $pdoLog->exec('PRAGMA foreign_keys = OFF;');
        foreach ($apiDb->tablesArr_log as $table => $columns) {
            $unsetId        = !empty($columns['unsetId']);
            $unsetTimestamp = !empty($columns['unsetTimestamp']);
            $pdoLog->exec(self::createTableSql($table, $columns, $unsetId, $unsetTimestamp));
        }

        self::seed($pdoDefault);
    }

    /** Seed minimal rows so the login flow works. */
    private static function seed(\PDO $pdo): void
    {
        // api_user_groups: id=1 is the admin group (rights=2 => administrator bit)
        $pdo->exec('INSERT OR IGNORE INTO "api_user_groups" ("id", "name", "rights", "default_flag") VALUES (1, \'Administrator\', 2, 1)');

        // api_users: id=1 admin / admin (password hash of 'admin')
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO "api_users" ("id", "username", "email", "password", "group", "changePasswd") VALUES (1, \'admin\', \'admin@example.com\', ?, 1, 0)');
        $stmt->execute([$hash]);

        // tac_global_settings: id=1 row (Controller::changeConfigurationFlag does TACGlobalConf::find(1))
        $pdo->exec('INSERT OR IGNORE INTO "tac_global_settings" ("id", "changeFlag", "revisionNum") VALUES (1, 0, 0)');

        // api_password_policy: id=1 (AuthController / TACUsersCtrl read APIPWPolicy::select()->first())
        $pdo->exec('INSERT OR IGNORE INTO "api_password_policy" ("id", "api_pw_length", "tac_pw_length") VALUES (1, 8, 8)');

        // api_settings: id=1
        $pdo->exec('INSERT OR IGNORE INTO "api_settings" ("id", "timezone") VALUES (1, 348)');
    }
}
