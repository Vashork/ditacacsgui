<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\StateGuard;
use tgui\Tests\Support\TestAppFactory;

/**
 * Regression guard for the factory lifecycle: building the app (or a
 * reset() + rebuild) in one case and dispatching again in a LATER case
 * must not fatal ("Constant APIVER already defined") and must not leak
 * state.
 *
 * On the REJECTED harness this fails: TestAppFactory::reset() nulled the
 * cached app and the next app() re-required bootstrap/app.php, whose
 * `require constants.php` re-defines constants -> PHP fatal.
 *
 * The two test methods below intentionally share the process-level app
 * cache: the first builds, the second rebuilds-after-reset, and a third
 * dispatches without any rebuild - proving the cached instance is
 * reusable across cases without re-requiring anything.
 */
final class FactoryLifecycleTest extends TestCase
{
    private StateGuard $guard;

    protected function setUp(): void
    {
        $this->guard = StateGuard::start();
    }

    protected function tearDown(): void
    {
        IsolatedEnvironment::restoreSuperglobals($this->guard->baselineState());
        $this->guard->verify();
    }

    public function testFirstBuildAndDispatch(): void
    {
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);
        [$status] = TestAppFactory::dispatch($app, 'GET', '/auth/signin/');
        $this->assertSame(401, $status, 'anonymous GET /auth/signin/ is 401 by design');
        $firstInstance = TestAppFactory::cachedApp();
        $this->assertSame($app, $firstInstance, 'app() must be idempotent');
        TestAppFactory::reset();
        $this->assertSame($firstInstance, TestAppFactory::cachedApp(), 'reset() must NOT drop the cached app (rebuild would re-require single-require files)');
    }

    public function testRebuildAfterResetDoesNotFatal(): void
    {
        // Must NOT fatal with "Constant APIVER already defined" and must
        // return a working app (proves bootstrap/constants are not
        // re-required on a second build).
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);
        [$status, $body] = TestAppFactory::dispatch($app, 'GET', '/auth/signin/');
        $this->assertSame(401, $status);
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'response must be JSON, got: ' . substr($body, 0, 200));
        $this->assertSame('empty', $decoded['info']['user']['id'] ?? null);
        TestAppFactory::reset();
    }

    public function testCachedAppIsReusableAcrossCases(): void
    {
        // After two prior cases (build + reset, rebuild + reset), the next
        // case must still get a working app with no re-require at all.
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);
        [$status] = TestAppFactory::dispatch($app, 'GET', '/auth/signin/');
        $this->assertSame(401, $status);
    }
}
