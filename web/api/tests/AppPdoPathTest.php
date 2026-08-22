<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\StateGuard;
use tgui\Tests\Support\TestAppFactory;

/**
 * Regression guard for defect 2 of the rejected harness: the app's LIVE
 * PDO connections must point at the two OS-temp SQLite files, not at the
 * hardcoded repo storage path. Proven through the app's own connections
 * (PRAGMA database_list), so 9p stat-cache staleness is irrelevant.
 */
final class AppPdoPathTest extends TestCase
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

    private static function norm(string $p): string
    {
        return str_replace('\\', '/', $p);
    }

    public function testDefaultConnectionPointsAtTempFile(): void
    {
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);

        $def = self::norm((string) ($_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? ''));
        $this->assertNotSame('', $def, 'TGUI_TEST_SQLITE_DEFAULT must be set by bootstrap');
        $root = self::norm(IsolatedEnvironment::runRoot());
        $this->assertStringStartsWith($root, $def, 'temp default db must live under the OS-temp run root');

        $capsule = $app->getContainer()->get('db');
        $pdo = $capsule->connection('default')->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
        $rows = $pdo->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $this->assertNotEmpty($rows, 'PRAGMA database_list must return rows');
        $file = self::norm((string) ($rows[0]['file'] ?? ''));
        $this->assertSame($def, $file, 'live default PDO must open the temp default file, got: ' . $file);
        $this->assertStringNotContainsString('web/api', $file, 'live default PDO must not point at repo storage');
        $this->assertStringNotContainsString('storage\sqlite', $file, 'live default PDO must not point at repo storage');
    }

    public function testLoggingConnectionPointsAtTempFile(): void
    {
        $app = TestAppFactory::app();

        $log = self::norm((string) ($_ENV['TGUI_TEST_SQLITE_LOG'] ?? ''));
        $this->assertNotSame('', $log, 'TGUI_TEST_SQLITE_LOG must be set by bootstrap');
        $root = self::norm(IsolatedEnvironment::runRoot());
        $this->assertStringStartsWith($root, $log, 'temp logging db must live under the OS-temp run root');

        $capsule = $app->getContainer()->get('db');
        $pdo = $capsule->connection('logging')->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
        $rows = $pdo->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $this->assertNotEmpty($rows, 'PRAGMA database_list must return rows');
        $file = self::norm((string) ($rows[0]['file'] ?? ''));
        $this->assertSame($log, $file, 'live logging PDO must open the temp logging file, got: ' . $file);
        $this->assertStringNotContainsString('web/api', $file, 'live logging PDO must not point at repo storage');
        $this->assertStringNotContainsString('storage\sqlite', $file, 'live logging PDO must not point at repo storage');

        // The two connections must be DISTINCT files.
        $defRows = $capsule->connection('default')->getPdo()->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $defFile = self::norm((string) ($defRows[0]['file'] ?? ''));
        $this->assertNotSame($defFile, $file, 'default and logging must be two distinct sqlite files');
    }
}
