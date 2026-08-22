<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\DbSetup;
use tgui\Tests\Support\IsolatedEnvironment;

/**
 * Task 2 failure-first sentinel (corrected): when the isolated SQLite
 * database file CANNOT be created, the harness's ACTUAL setup seam
 * (DbSetup::prepareSqliteFile - the same method tests/bootstrap.php calls
 * for the two per-run files) must fail with a CONTROLLED, explicit
 * RuntimeException naming the offending path. It must NEVER be a raw
 * PDO/MySQL exception and must never imply a MySQL fallback.
 */
final class UnwritableDbSentinelTest extends TestCase
{
    public function testUnwritableSqlitePathFailsControlledWithoutMysqlFallback(): void
    {
        $root = IsolatedEnvironment::runRoot();
        $this->assertNotNull($root);

        // Portable unwritable sentinel: a REGULAR FILE occupies the parent
        // directory name, so the seam's mkdir must fail. (On POSIX a 0555
        // directory gives the same effect, but on this 9p Windows workspace
        // permission bits are not enforced, so the file-occupied form is
        // the portable one.)
        $victimPath = $root . '/sentinel-sqlite-dir';
        $this->assertTrue((bool) @file_put_contents($victimPath, 'not-a-dir'), 'cannot create sentinel file');

        try {
            $caught = null;
            try {
                DbSetup::prepareSqliteFile($victimPath . '/tgui.sqlite');
            } catch (\Throwable $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, 'unwritable sqlite path must raise a controlled exception, not fall back to anything');
            $this->assertNotInstanceOf(\PDOException::class, $caught, 'failure must be the controlled RuntimeException, not a raw PDO exception (which could hide a driver fallback)');
            $msg = get_class($caught) . ': ' . $caught->getMessage();
            // Strip the seam's own "no MySQL fallback" disclaimer before
            // asserting that no MySQL attempt is mentioned.
            $msg = str_ireplace('no MySQL fallback', '', $msg);
            $this->assertStringNotContainsString('mysql', strtolower($msg), 'failure must not mention a MySQL connection attempt: ' . $msg);
            $this->assertStringContainsString($victimPath, $msg, 'failure must name the offending path: ' . $msg);
        } finally {
            @unlink($victimPath);
        }
    }

    public function testBootstrapEnvPointsOnlyAtIsolatedRoot(): void
    {
        // The only sqlite paths in the environment are the two isolated
        // temp files; there is no env path that could redirect the app to
        // repo storage or /opt.
        $root = str_replace('\\', '/', (string) IsolatedEnvironment::runRoot());
        $def = str_replace('\\', '/', (string) ($_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? ''));
        $log = str_replace('\\', '/', (string) ($_ENV['TGUI_TEST_SQLITE_LOG'] ?? ''));
        $this->assertStringStartsWith($root, $def);
        $this->assertStringStartsWith($root, $log);
        $this->assertSame('sqlite', getenv('DB_DRIVER'));
    }
}
