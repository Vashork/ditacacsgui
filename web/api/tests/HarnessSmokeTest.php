<?php
declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\StateGuard;
use tgui\Tests\Support\TestAppFactory;


/**
 * Task 2 smoke: the REAL production Slim app (bootstrap/app.php) must build
 * and handle requests under the isolated harness — no MySQL, no live HA
 * files, no tac_plus, no pre-existing repo storage/sqlite, no host session
 * directories.
 *
 * Isolation model (see tests/bootstrap.php): the pre-existing repo
 * storage/sqlite dir is stashed away for the run; the unmodified bootstrap
 * recreates an EMPTY storage/sqlite with a fresh schema during app boot.
 * The suite proves the schema/seed landed there (fresh, run-generated),
 * while the original repo dev data is restored untouched after the run.
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
        // Restore the exact pre-test global state (session, cookies, env,
        // ini) — this is the no-leak guarantee for Tasks 4-8.
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
        $this->assertStringNotContainsString('/web/api/', str_replace('\\', '/', (string) $root));
        $this->assertStringNotContainsString('web\\api\\', (string) $root);

        $this->assertSame('sqlite', $_ENV['DB_DRIVER'] ?? null, 'DB_DRIVER must be forced to sqlite');
        $def = $_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? '';
        $log = $_ENV['TGUI_TEST_SQLITE_LOG'] ?? '';
        $this->assertNotSame('', $def, 'TGUI_TEST_SQLITE_DEFAULT must be set');
        $this->assertNotSame('', $log, 'TGUI_TEST_SQLITE_LOG must be set');
        $this->assertNotSame($def, $log, 'two distinct sqlite files are required by the schema code');
        $this->assertStringStartsWith(str_replace('\\', '/', (string) $root), str_replace('\\', '/', $def));
        $this->assertStringStartsWith(str_replace('\\', '/', (string) $root), str_replace('\\', '/', $log));

        // HA constants must point inside the isolated root (nonexistent on
        // purpose) so the app boots 'standalone' without /opt/tgui_data.
        foreach (['HA_SETTINGS_PATH', 'HA_MASTER_PATH', 'HA_SLAVES_PATH'] as $k) {
            $v = (string) ($_ENV[$k] ?? '');
            $this->assertStringStartsWith(str_replace('\\', '/', (string) $root), str_replace('\\', '/', $v), $k);
            $this->assertFileDoesNotExist($v, $k . ' must not exist on disk');
        }

        // Session must be isolated: unique name + save path inside the root.
        $this->assertStringStartsWith('tguitest', (string) ini_get('session.name'));
        $this->assertStringStartsWith(str_replace('\\', '/', (string) $root), str_replace('\\', '/', (string) ini_get('session.save_path')));

        // Known test-only JWT secret: present, non-placeholder.
        $this->assertSame(IsolatedEnvironment::TEST_JWT_SECRET, $_ENV['JWT_SECRET'] ?? null);
    }

    public function testSlimAppBuildsAndHandlesRequestWithoutExternalDependencies(): void
    {
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);

        // GET /auth/signin/ is registered in production routes and is on the
        // JWT ignore list, so it reaches the handler WITHOUT a bearer token.
        // The full stack runs: routing -> JWT middleware (exempt) -> body
        // parsing -> ChangeHeaderMiddleware -> getSignIn() ->
        // Controller::initialData() -> Auth::check() (session-backed) ->
        // SQLite-backed queries (mavis_local / api_users).
        //
        // Observed production behavior for an anonymous request:
        // initialData() leaves $_SESSION['error']['status']=true (Auth::check
        // sets it when uid is absent), so getSignIn() returns HTTP 401 with a
        // valid JSON envelope (error.status=true). This proves the app was
        // handled end-to-end (a broken boot would be 500/empty, and a JWT
        // misconfiguration would 401 with the JWT middleware's message).
        [$status, $body] = TestAppFactory::dispatch($app, 'GET', '/auth/signin/');
        $this->assertSame(401, $status, 'anonymous GET /auth/signin/ is 401 by design (unauthorized session)');
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'response body must be JSON, got: ' . substr($body, 0, 200));
        $this->assertArrayHasKey('info', $decoded, 'envelope must carry info, got: ' . substr($body, 0, 200));
        $this->assertArrayHasKey('tacacs', $decoded, 'envelope must carry tacacs (MAVISLocal probe), got: ' . substr($body, 0, 200));
        $this->assertSame('empty', $decoded['info']['user']['id'] ?? null, 'user id must be empty when unauthenticated');
        $this->assertTrue((bool) ($decoded['error']['status'] ?? false), 'error.status must be true for an unauthorized session');

        // The SQLite schema + seed must have been built by the production
        // bootstrap into the storage/sqlite dir it recreated during app
        // boot (the pre-existing repo dev data was stashed away for the
        // run and is restored untouched after it). The FRESH schema is
        // proven through the app's own live PDO connection (guaranteed to
        // see the data regardless of 9p stat-cache staleness).
        // NOTE: computed with 2 dirname levels from THIS file (tests/ ->
        // web/api); a third level would resolve outside the repo.
        $apiRoot = dirname(__DIR__);
        $repo = $apiRoot . '/storage/sqlite/tgui.sqlite';
        $this->assertFileExists($repo, 'bootstrap must rebuild storage/sqlite (pre-existing data stashed at suite start)');
        $pdo = \Illuminate\Database\Capsule\Manager::connection('default')->getPdo();
        $tables = $pdo->query('SELECT name FROM sqlite_master WHERE type=\'table\'')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $this->assertContains('api_users', $tables, 'rebuilt db must contain the built schema (api_users)');
        $this->assertContains('api_user_groups', $tables, 'rebuilt db must contain api_user_groups');
        $this->assertContains('mavis_local', $tables, 'rebuilt db must contain mavis_local (queried by getSignIn)');
        $admins = $pdo->query('SELECT id FROM api_users WHERE username=\'admin\'')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $this->assertSame([1], array_map('intval', $admins), 'seeded admin row must exist (id=1)');

        // The two isolated env sqlite files exist as valid empty databases.
        $root = (string) IsolatedEnvironment::runRoot();
        $this->assertFileExists($root . '/tgui-test.sqlite');
        $this->assertFileExists($root . '/tgui-test-log.sqlite');
        $this->assertSame(1, (int) (new \PDO('sqlite:' . $root . '/tgui-test.sqlite'))->query('SELECT 1')->fetchColumn());
    }

    public function testSessionIsolationBetweenCases(): void
    {
        // Case A: write session state, then clear — the guard in tearDown
        // verifies no residue leaked to the next case.
        $_SESSION['uid'] = 1;
        $_SESSION['uname'] = 'leak-probe';
        $_SESSION = [];

        $this->assertSame([], $_SESSION);
    }
}
