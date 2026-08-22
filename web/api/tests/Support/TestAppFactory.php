<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

    /**
     * Builds the REAL production Slim app (bootstrap/app.php) under the isolated
     * harness environment, and dispatches synthetic requests through it
     * in-process (no HTTP server, no MySQL, no live HA files).
     *
     * The bootstrap is required with a guarded eval of its trailing
     * `return $app;` so the returned App instance is usable in tests while the
     * product file itself stays byte-for-byte unmodified.
     *
     * The app is built ONCE per process: the bootstrap defines constants
     * (constants.php) and re-requiring it would fatal. Tests that need a
     * "fresh" app call reset() and reuse the cached instance; true per-case
     * process isolation is available via phpunit.xml's processIsolation for
     * tests that require it.
     */
    final class TestAppFactory
    {
        private static ?App $app = null;
        /** Whether assertSqliteIsolation() has already passed this process. */
        private static bool $isolationChecked = false;

        /**
         * Build (once per process) and return the production Slim app.
         *
         * @throws RuntimeException when the app cannot be built (fail-closed;
         *         callers never fall back to another database driver).
         */
        public static function app(): App
        {
            if (self::$app !== null) {
                return self::$app;
            }

        $runRoot = IsolatedEnvironment::runRoot();
        if ($runRoot === null || !is_dir($runRoot)) {
            throw new RuntimeException('TestAppFactory::app() called before bootstrap created the run root.');
        }

        // Fail closed BEFORE the FIRST app boot of this process: the repo
        // sqlite directory must be absent (stashed by tests/bootstrap.php).
        // After the first boot the unmodified bootstrap legitimately
        // recreates it with FRESH run-generated data (teardown removes it),
        // so the check runs exactly once per process.
        if (!self::$isolationChecked) {
            self::assertSqliteIsolation();
            self::$isolationChecked = true;
        }

        // The env-level sqlite paths remain part of the deterministic
        // environment contract (Tasks 4-8) and must resolve inside the run
        // root; they are validated by the bootstrap + smoke assertions.
        $def = (string) ($_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? '');
        $log = (string) ($_ENV['TGUI_TEST_SQLITE_LOG'] ?? '');
        if ($def === '' || $log === '') {
            throw new RuntimeException('TGUI_TEST_SQLITE_DEFAULT/TGUI_TEST_SQLITE_LOG env not set by bootstrap.');
        }

        $bootstrap = dirname(__DIR__, 2) . '/bootstrap/app.php';
        if (!is_file($bootstrap)) {
            throw new RuntimeException('bootstrap/app.php not found at ' . $bootstrap);
        }

        if (!self::$isolationChecked) {
            // First boot of this process: the unmodified bootstrap will
            // recreate the (stashed-away) repo storage/sqlite directory with
            // fresh run-generated data. Remember that so a later reset()
            // re-build does not mistake it for an isolation breach.
            self::$isolationChecked = true;
        }
        $app = eval('return require ' . var_export($bootstrap, true) . ';');
        if (!$app instanceof App) {
            throw new RuntimeException('bootstrap/app.php did not return a Slim\\App instance.');
        }
        self::$app = $app;
        return $app;
    }

    /**
     * Reset the cached app (test isolation between cases). The bootstrap is
     * not re-required (constants would be redefined); a fresh request is
     * dispatched against the same app instance with a clean session state.
     */
    public static function reset(): void
    {
        self::$app = null;
    }

    /**
     * Fail-closed precondition for the SQLite branch of the production
     * bootstrap. Because bootstrap/app.php resolves its sqlite directory as
     * __DIR__/../storage/sqlite (repo path, not env), the harness keeps that
     * directory ABSENT for the whole run (tests/bootstrap.php stashes it)
     * and the suite asserts its absence here. This guarantees the bootstrap
     * cannot read/write repo dev data; its own @mkdir + touch then recreate
     * an EMPTY storage/sqlite that is captured (hash-compared) by the QA
     * wrapper and removed during cleanup. Any presence of the stashed repo
     * dir at this point is a controlled failure.
     */
    public static function assertSqliteIsolation(): void
    {
        $repo = dirname(__DIR__, 2) . '/storage/sqlite';
        if (is_dir($repo)) {
            throw new RuntimeException('Repo storage/sqlite is present during the suite (isolation broken; refusing to run, no MySQL fallback): ' . $repo);
        }
    }

    /**
     * Dispatch a synthetic request through the full middleware stack.
     * Returns [status, body-string, response].
     *
     * @return array{0:int, 1:string, 2:\Psr\Http\Message\ResponseInterface}
     */
    public static function dispatch(
        App $app,
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
        array $headers = [],
        array $cookies = []
    ): array {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path . ($query === [] ? '' : '?' . http_build_query($query)));
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }
        foreach ($cookies as $k => $v) {
            $request = $request->withCookieParams(array_merge($request->getCookieParams(), [$k => $v]));
        }
        if ($json !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withBody((new StreamFactory())->createStream((string) json_encode($json)));
        }

        $response = $app->handle($request);
        $body = (string) $response->getBody();
        return [$response->getStatusCode(), $body, $response];
    }
}
