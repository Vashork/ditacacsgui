<?php

declare(strict_types=1);

/**
 * Isolated integration-test bootstrap (Task 2 harness, corrected).
 *
 * Contract (fail-closed, OS-temp only):
 *  - Every test PROCESS gets its own unique temp root under the OS temp dir.
 *    This bootstrap never reads, writes, renames, deletes, or restores ANY
 *    repository path - in particular not web/api/storage/sqlite.
 *  - The two OS-temp SQLite files (default + logging) are created and
 *    verified by the shared seam DbSetup::prepareSqliteFile(); the
 *    production SchemaBuilderSqlite builds the schema into them (driven by
 *    TestAppFactory, not here).
 *  - DB_DRIVER=sqlite, TGUI_TEST_SQLITE_*, HA/TAC path env vars, and a known
 *    test-only JWT secret are forced in-process (web/api/.env on disk is
 *    never modified).
 *  - Native sessions are pointed at a temp save_path with a unique name.
 *  - Teardown removes the temp root. Nothing else is touched.
 *
 * The Slim app itself is built by tgui\Tests\Support\TestAppFactory, which
 * assembles the production surface (DI bindings, middleware,
 * registerRoutes) WITHOUT requiring bootstrap/app.php - so the hardcoded
 * repo storage path in that file is never executed.
 */

use tgui\Tests\Support\DbSetup;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\EnvSnapshot;

require __DIR__ . '/../vendor/autoload.php';

// Platform gate: pdo_sqlite must be present in the PHP that runs the suite
// (Windows CLI has it; the WSL CLI build does not). Fail closed.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite is not available in this PHP build; the isolated harness cannot run (no MySQL fallback).');
}

// 1. Unique per-run temp root under the OS temp dir (fail-closed creation).
$runId = bin2hex(random_bytes(4)) . '-' . getmypid();
$runRoot = IsolatedEnvironment::createTempRoot('tgui-phpunit-' . $runId, 5);

// 2. Register teardown BEFORE anything else can throw: remove the temp root.
IsolatedEnvironment::setRunContext($runRoot);
$teardown = static function (): void {
    IsolatedEnvironment::removeTree(IsolatedEnvironment::runRoot());
};
register_shutdown_function($teardown);

// 3. Force deterministic env values. EnvSnapshot captures the pre-test
//    state of the globals the suite may touch so tests (and Tasks 4-8) can
//    restore exact values instead of leaking between cases.
$snapshot = new EnvSnapshot([
    'DB_DRIVER',
    'TGUI_TEST_SQLITE_DEFAULT',
    'TGUI_TEST_SQLITE_LOG',
    'HA_SETTINGS_PATH',
    'HA_MASTER_PATH',
    'HA_SLAVES_PATH',
    'TAC_ROOT_PATH',
    'JWT_SECRET',
    'JWT_ALGORITHM',
]);
$snapshot->apply([
    // The harness app factory is sqlite-only (no MySQL branch at all).
    'DB_DRIVER' => 'sqlite',
    // The two OS-temp database files the factory's connections point at.
    'TGUI_TEST_SQLITE_DEFAULT' => $runRoot . '/tgui-test.sqlite',
    'TGUI_TEST_SQLITE_LOG' => $runRoot . '/tgui-test-log.sqlite',
    // HA/TAC path env vars point at nonexistent temp locations so
    // constants.php resolves them inside the isolated root (the app boots
    // 'standalone' without touching /opt/tgui_data or /opt/tacacsgui).
    'HA_SETTINGS_PATH' => $runRoot . '/ha/ha-settings.yaml',
    'HA_MASTER_PATH' => $runRoot . '/ha/ha-master.yaml',
    'HA_SLAVES_PATH' => $runRoot . '/ha/ha-slaves.yaml',
    'TAC_ROOT_PATH' => $runRoot . '/tacacsgui',
    // Known test-only JWT secret (NOT a placeholder, NOT the dev value).
    'JWT_SECRET' => IsolatedEnvironment::TEST_JWT_SECRET,
    'JWT_ALGORITHM' => 'HS256',
]);

// 4. Create + verify the two isolated SQLite files through the shared seam
//    (the SAME code path the sentinel test drives; fail closed).
foreach (['TGUI_TEST_SQLITE_DEFAULT', 'TGUI_TEST_SQLITE_LOG'] as $key) {
    DbSetup::prepareSqliteFile((string) $_ENV[$key]);
}

// 5. Unique native-session configuration (name/save path) in this process.
IsolatedEnvironment::configureTestSession($runRoot);

// 6. Expose the run context to tests.
IsolatedEnvironment::setRunContext($runRoot, $snapshot);
