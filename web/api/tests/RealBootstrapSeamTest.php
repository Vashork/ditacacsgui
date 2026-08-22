<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\TestAppFactory;

/**
 * F-1 regression (copied-bootstrap drift): the harness must execute the REAL
 * production app-construction code in web/api/bootstrap/app.php, not a
 * duplicated clone.
 *
 * On the REJECTED harness (c81397f) this FAILS at both markers:
 *  - the 'db' container binding was a factory-local closure (getClosure()
 *    === false; the factory duplicated the DI assembly), and
 *  - TGUI_TEST_SQLITE_DEFAULT was not read anywhere in bootstrap/app.php
 *    (the bootstrap hardcoded the repo storage path, so the factory bypassed
 *    it entirely and the real script could not be required under test).
 *
 * After the correction: the factory requires bootstrap/app.php (via
 * tgui_build_app()), and the sqlite branch of that file resolves the two
 * database files from TGUI_TEST_SQLITE_DEFAULT/TGUI_TEST_SQLITE_LOG when
 * APP_ENV=testing - so a change to the production construction code is
 * executed (and observable) by this suite.
 */
final class RealBootstrapSeamTest extends TestCase
{
    public function testAppIsBuiltByProductionBootstrapCode(): void
    {
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);

        // The 'db' binding must be a closure DEFINED INSIDE
        // bootstrap/app.php (production construction code), not a
        // factory-local duplicate. PHP-DI stores explicit set() entries in
        // the mutable DefinitionArray (reached via the private
        // $definitionSource); a factory-local binding would point at a
        // tests/ file instead of bootstrap/app.php.
        $capsule = $app->getContainer()->get('db');
        $this->assertInstanceOf(\Illuminate\Database\Capsule\Manager::class, $capsule);
        // The DI container's definition source (reached via the private
        // $definitionSource; it is a SourceChain whose getDefinitions()
        // merges every registered source) must expose 'db' as a closure
        // DEFINED INSIDE bootstrap/app.php (production construction code),
        // not a factory-local duplicate.
        $refl = new \ReflectionProperty($app->getContainer(), 'definitionSource');
        $refl->setAccessible(true);
        $defs = $refl->getValue($app->getContainer())->getDefinitions();
        $this->assertArrayHasKey('db', $defs, 'production bootstrap must define the db binding');
        $def = $defs['db'];
        // PHP-DI normalizes set() closures into FactoryDefinition; recover
        // the original closure from its 'factory' property.
        $closure = $def;
        if ($def instanceof \DI\Definition\FactoryDefinition) {
            $prop = new \ReflectionProperty(\DI\Definition\FactoryDefinition::class, 'factory');
            $prop->setAccessible(true);
            $closure = $prop->getValue($def);
        }
        $this->assertTrue(
            $closure instanceof \Closure,
            'the db binding must be a production closure (DI assembly must come from bootstrap/app.php, not a test duplicate)'
        );
        $file = (string) (new \ReflectionFunction($closure))->getFileName();
        $this->assertStringEndsWith('bootstrap/app.php', str_replace('\\', '/', $file), 'the db closure must be defined in bootstrap/app.php, got: ' . $file);
    }

    public function testBootstrapResolvesTestSqlitePathsFromEnv(): void
    {
        // The production sqlite branch must resolve the two database files
        // from the test env paths when APP_ENV=testing (the seam). If the
        // bootstrap hardcodes the repo path (as in c81397f), this file does
        // not contain the env keys and the suite would be proving nothing
        // about the real construction code.
        $bootstrap = dirname(__DIR__) . '/bootstrap/app.php';
        $src = (string) file_get_contents($bootstrap);
        $this->assertStringContainsString('TGUI_TEST_SQLITE_DEFAULT', $src, 'bootstrap/app.php must resolve the default sqlite file from TGUI_TEST_SQLITE_DEFAULT under the test gate');
        $this->assertStringContainsString('TGUI_TEST_SQLITE_LOG', $src, 'bootstrap/app.php must resolve the logging sqlite file from TGUI_TEST_SQLITE_LOG under the test gate');
        $this->assertStringContainsString('APP_ENV', $src, 'bootstrap/app.php must gate the test-only path on APP_ENV=testing (no general-purpose runtime knob)');
    }
}
