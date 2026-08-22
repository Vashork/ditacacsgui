<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\StateGuard;
use tgui\Tests\Support\TestAppFactory;

/**
 * Task 2 smoke (corrected): the production-surface Slim app must build and
 * handle requests with BOTH PDO connections pointed at the two OS-temp
 * SQLite files. The repository storage directory is never referenced by
 * this suite (see RepoStorageGuardTest for the negative guard).
 */
final class HarnessSmokeTest extends TestCase
{
    private StateGuard $guard;

    protected function setUp(): void
    {
        $this->guard = StateGuard::start();
    }

    protected function tearDown(): void
    {
        IsolatedEnvironment::restoreSuperglobals($this->guard->baselineState());
        TestAppFactory::reset();
        $this->guard->verify();
    }

    public function testBootstrapIsolatesEnvironment(): void
    {
        $root = IsolatedEnvironment::runRoot();
        $this->assertNotNull($root, 'run root must be created by tests/bootstrap.php');
        $this->assertTrue(is_dir($root), 'run root must be a directory');
        // OS temp dir, NOT the repository.
        $this->assertStringNotContainsString('/web/api/', str_replace('\\', '/', $root));
        $this->assertStringNotContainsString('web\\api\\', $root);

        $this->assertSame('sqlite', $_ENV['DB_DRIVER'] ?? null, 'DB_DRIVER must be forced to sqlite');
        $def = (string) ($_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? '');
        $log = (string) ($_ENV['TGUI_TEST_SQLITE_LOG'] ?? '');
        $this->assertNotSame('', $def, 'TGUI_TEST_SQLITE_DEFAULT must be set');
        $this->assertNotSame('', $log, 'TGUI_TEST_SQLITE_LOG must be set');
        $this->assertNotSame($def, $log, 'two distinct sqlite files are required by the schema code');
        $rootNorm = str_replace('\\', '/', $root);
        $this->assertStringStartsWith($rootNorm, str_replace('\\', '/', $def));
        $this->assertStringStartsWith($rootNorm, str_replace('\\', '/', $log));

        // HA path constants must point inside the isolated root (absent on
        // purpose) so the app boots 'standalone' without /opt/tgui_data.
        foreach (['HA_SETTINGS_PATH', 'HA_MASTER_PATH', 'HA_SLAVES_PATH'] as $k) {
            $v = (string) ($_ENV[$k] ?? '');
            $this->assertStringStartsWith($rootNorm, str_replace('\\', '/', $v), $k);
            $this->assertFileDoesNotExist($v, $k . ' must not exist on disk');
        }

        // Session must be isolated: unique name + save path inside the root.
        $this->assertStringStartsWith('tguitest', (string) ini_get('session.name'));
        $this->assertStringStartsWith($rootNorm, str_replace('\\', '/', (string) ini_get('session.save_path')));

        // Known test-only JWT secret: present, non-placeholder.
        $this->assertSame(IsolatedEnvironment::TEST_JWT_SECRET, $_ENV['JWT_SECRET'] ?? null);
    }

    public function testAppUsesTempSqliteFilesAndHandlesRequest(): void
    {
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);

        // Live PDO proof: both connections must open the two temp files.
        $capsule = $app->getContainer()->get('db');
        $rootNorm = str_replace('\\', '/', IsolatedEnvironment::runRoot());
        $def = str_replace('\\', '/', (string) ($_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? ''));
        $log = str_replace('\\', '/', (string) ($_ENV['TGUI_TEST_SQLITE_LOG'] ?? ''));
        foreach ([
            ['default', $def, $capsule->connection('default')->getPdo()],
            ['logging', $log, $capsule->connection('logging')->getPdo()],
        ] as [$label, $expected, $pdo]) {
            $rows = $pdo->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $file = str_replace('\\', '/', (string) ($rows[0]['file'] ?? ''));
            $this->assertSame($expected, $file, "live {$label} PDO must open the temp file, got: {$file}");
            $this->assertStringStartsWith($rootNorm, $file, "live {$label} PDO must live under the OS-temp root");
        }

        // The production schema + seed must be built into the temp default
        // file (via the app's own connection, immune to 9p stat staleness).
        $pdo = $capsule->connection('default')->getPdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach (['api_users', 'api_user_groups', 'mavis_local', 'tac_global_settings'] as $t) {
            $this->assertContains($t, $tables, "schema must contain {$t}");
        }
        $admins = $pdo->query("SELECT id FROM api_users WHERE username='admin'")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $this->assertSame([1], array_map('intval', $admins), 'seeded admin row must exist (id=1)');

        // End-to-end request: GET /auth/signin/ is on the JWT ignore list,
        // so the full stack (routing -> JWT -> body parsing ->
        // ChangeHeaderMiddleware -> getSignIn -> session + SQLite queries)
        // runs. Anonymous production behavior: 401 + JSON envelope.
        [$status, $body] = TestAppFactory::dispatch($app, 'GET', '/auth/signin/');
        $this->assertSame(401, $status, 'anonymous GET /auth/signin/ is 401 by design (unauthorized session)');
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'response body must be JSON, got: ' . substr($body, 0, 200));
        $this->assertArrayHasKey('info', $decoded, 'envelope must carry info, got: ' . substr($body, 0, 200));
        $this->assertArrayHasKey('tacacs', $decoded, 'envelope must carry tacacs (MAVISLocal probe), got: ' . substr($body, 0, 200));
        $this->assertSame('empty', $decoded['info']['user']['id'] ?? null, 'user id must be empty when unauthenticated');
        $this->assertTrue((bool) ($decoded['error']['status'] ?? false), 'error.status must be true for an unauthorized session');
    }

    public function testSessionIsolationBetweenCases(): void
    {
        // Case A: write session state, then clear - the guard in tearDown
        // verifies no residue leaked to the next case.
        $_SESSION['uid'] = 1;
        $_SESSION['uname'] = 'leak-probe';
        $_SESSION = [];
        $this->assertSame([], $_SESSION);
    }
}
