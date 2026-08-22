<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\DbSetup;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\TestAppFactory;

/**
 * Regression guard for the DB setup seam (defect 4 of the rejected
 * harness): the sentinel must exercise the ACTUAL seam the bootstrap
 * uses (DbSetup::prepareSqliteFile), not a hand-mirrored copy of
 * mkdir/touch/PDO statements inside the test.
 *
 * On the REJECTED harness this fails (PHPUnit "test was able to execute
 * normally" + missing exception) because prepareSqliteFile() did not
 * exist; the old test only proved its own mirrored statements fail.
 */
final class DbSetupSeamTest extends TestCase
{
    public function testSeamFailsClosedOnUnwritablePath(): void
    {
        $root = IsolatedEnvironment::runRoot();

        // Portable unwritable sentinel: a REGULAR FILE occupies the
        // parent directory name, so the seam's mkdir must fail.
        $victim = $root . '/seam-sentinel-dir';
        $this->assertFileDoesNotExist($victim);
        $this->assertTrue((bool) @file_put_contents($victim, 'not-a-dir'), 'cannot create sentinel file');

        try {
            $caught = null;
            try {
                DbSetup::prepareSqliteFile($victim . '/tgui.sqlite');
            } catch (\Throwable $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, 'DbSetup::prepareSqliteFile must throw on an unwritable path (fail-closed)');
            $this->assertNotInstanceOf(\PDOException::class, $caught, 'unwritable path must yield the controlled RuntimeException, not a raw PDO/MySQL exception');
            $msg = get_class($caught) . ': ' . $caught->getMessage();
            // The controlled message says "no MySQL fallback" - strip that
            // disclaimer before asserting no MySQL attempt occurred.
            $msg = str_ireplace('no MySQL fallback', '', $msg);
            $this->assertStringNotContainsString('mysql', strtolower($msg), 'failure must never involve a MySQL connection attempt: ' . $msg);
            $this->assertStringContainsString($victim, $msg, 'failure must name the offending path: ' . $msg);
        } finally {
            @unlink($victim);
        }
    }

    public function testSeamCreatesUsableDatabase(): void
    {
        $root = IsolatedEnvironment::runRoot();
        $file = $root . '/seam-ok.sqlite';
        if (is_file($file)) {
            @unlink($file);
        }

        DbSetup::prepareSqliteFile($file);

        // The produced file must be a real, openable SQLite database.
        $pdo = new \PDO('sqlite:' . $file);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $pdo = null;
        $this->assertContains('_harness_init', $tables, 'seam must produce a real empty SQLite database');
        @unlink($file);
    }
}
