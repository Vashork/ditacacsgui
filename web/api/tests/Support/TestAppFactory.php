<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Top-level bootstrap capture. The bootstrap script ends with
 * `return $app;` - a require inside a method silently drops that value
 * (a method cannot return via a required file's `return`), but a require
 * at TOP-LEVEL scope lets the script's `return` flow out as the require
 * expression's value. This function is deliberately minimal: its only job
 * is to require the real production bootstrap and hand back its result.
 *
 * @param string $bootstrap absolute path to bootstrap/app.php
 * @return mixed whatever bootstrap/app.php returns (a Slim App)
 */
function tgui_tests_require_bootstrap(string $bootstrap)
{
    $level = ob_get_level();
    ob_start();
    try {
        return require $bootstrap;
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }
}

/**
 * Test-only facade over the REAL production app builder.
 *
 * The app is constructed by REQUIRING web/api/bootstrap/app.php (the real
 * production entry point) with APP_ENV=testing set - the SAME code the
 * runtime executes, including its DI assembly, controller map, JWT
 * configuration, middleware order, and registerRoutes call. The only
 * injection is the two OS-temp SQLite file paths (the bootstrap resolves
 * them from TGUI_TEST_SQLITE_* under the APP_ENV=testing gate and fails
 * closed otherwise). This factory holds NO copied production construction
 * code: a production bootstrap edit (Tasks 5/7/8) is executed - and
 * observable - by this suite.
 *
 * Lifecycle (per process): app() builds ONCE (the bootstrap re-require would
 * re-define constants and fatal); reset() clears per-case request state
 * (session/cookies) and reuses the cached instance. A fresh phpunit run is
 * the fresh-process boundary.
 */
final class TestAppFactory
{
    private static ?App $app = null;

    /**
     * Build (once per process, via the production bootstrap) and return the
     * Slim app wired to the two OS-temp SQLite files.
     *
     * @throws RuntimeException fail-closed when the app cannot be built
     */
    public static function app(): App
    {
        if (self::$app !== null) {
            return self::$app;
        }

        $apiRoot = dirname(__DIR__, 2);
        $bootstrap = $apiRoot . '/bootstrap/app.php';
        if (!is_file($bootstrap)) {
            throw new RuntimeException('bootstrap/app.php not found at ' . $bootstrap);
        }

        // The test-only gate (enforced inside the bootstrap's sqlite branch):
        // without APP_ENV=testing the repo storage paths would be used.
        \putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        // The bootstrap is a SCRIPT ending with `return $app;` - required
        // through the top-level helper tgui_tests_require_bootstrap() so
        // the script's return is captured (a method-scoped require would
        // drop it). Output is buffered around the construction so any stray
        // echo from the production script cannot poison test output
        // (discarded, never printed).
        self::$app = \tgui\Tests\Support\tgui_tests_require_bootstrap($bootstrap);
        if (!self::$app instanceof App) {
            throw new RuntimeException('bootstrap/app.php did not produce a Slim\\App instance.');
        }
        return self::$app;
    }

    /**
     * Per-case reset: clear request state (session/cookies) so the next
     * dispatch starts fresh. The cached app is reused - the bootstrap cannot
     * be re-required in-process (single-require constants), and a fresh
     * process is what a new phpunit run provides.
     */
    public static function reset(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
    }

    /** @return ?App the cached instance (null before the first build) */
    public static function cachedApp(): ?App
    {
        return self::$app;
    }

    /**
     * Dispatch a synthetic request through the full production middleware
     * stack.
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
