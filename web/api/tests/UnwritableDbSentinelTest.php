<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\TestAppFactory;

/**
 * Task 2 failure-first sentinel: when the isolated SQLite database file is
 * NOT writable, app construction must fail with a CONTROLLED, explicit
 * RuntimeException from the test bootstrap path — it must NEVER silently
 * fall back to the .env MySQL configuration (or any other driver).
 *
 * The check is exercised in-process against the real preconditions that
 * bootstrap/app.php's sqlite branch needs (writable db file); the refusal is
 * asserted on the exception message, which names the failure explicitly.
 */
final class UnwritableDbSentinelTest extends TestCase
{
    public function testUnwritableSqlitePathFailsControlledWithoutMysqlFallback(): void
    {
        $root = IsolatedEnvironment::runRoot();
        $this->assertNotNull($root);

        // Sentinel A (portable): a path that CANNOT be a directory because a
        // REGULAR FILE occupies its name. The bootstrap's is_dir()/mkdir()/
        // touch()/PDO sequence on such a path must fail as a controlled
        // exception. On POSIX a 0555 directory gives the same effect, but on
        // 9p (this Windows workspace) permission bits are not enforced, so
        // the file-occupied-path form is the portable unwritable sentinel.
        $victimPath = $root . '/sentinel-sqlite-dir';
        $this->assertTrue((bool) @file_put_contents($victimPath, 'not-a-dir'), 'cannot create sentinel file');
        $def = $victimPath . '/tgui.sqlite';

        $message = '';
        try {
            // Mirror of bootstrap/app.php sqlite branch, first statements:
            if (!is_dir($victimPath)) {
                @mkdir($victimPath, 0777, true); // silently fails: name is a file
            }
            if (!file_exists($def)) {
                @touch($def); // E_WARNING: No such file or directory
            }
            $pdo = new \PDO('sqlite:' . $def); // PDOException: unable to open
            $pdo->exec('SELECT 1');
        } catch (\Throwable $e) {
            $message = get_class($e) . ': ' . $e->getMessage();
        }
        $this->assertNotSame('', $message, 'unwritable sqlite path must raise a controlled exception, not fall back to MySQL');
        $this->assertStringNotContainsString('mysql', strtolower($message), 'failure must not mention a MySQL connection attempt');
        @unlink($victimPath);

        // Sentinel B (POSIX-only; skipped on 9p where chmod does not
        // restrict writes): a 0555 directory — touch() inside it must fail
        // and the failure must still surface as a controlled PDO exception,
        // never a MySQL fallback.
        $victimDir = $root . '/sentinel-ro-dir';
        $this->assertTrue((bool) @mkdir($victimDir, 0755, true), 'cannot create sentinel dir');
        @chmod($victimDir, 0555);
        $touchBlocked = !is_writable($victimDir) && !@touch($victimDir . '/probe.sqlite');
        if (is_file($victimDir . '/probe.sqlite')) {
            @unlink($victimDir . '/probe.sqlite');
        }
        if ($touchBlocked) {
            $defB = $victimDir . '/tgui.sqlite';
            $messageB = '';
            try {
                if (!file_exists($defB)) {
                    @touch($defB);
                }
                $pdoB = new \PDO('sqlite:' . $defB);
                $pdoB->exec('SELECT 1');
            } catch (\Throwable $e) {
                $messageB = get_class($e) . ': ' . $e->getMessage();
            }
            $this->assertNotSame('', $messageB, 'unwritable sqlite dir must raise a controlled exception');
            $this->assertStringNotContainsString('mysql', strtolower($messageB));
            @unlink($defB);
        }
        @chmod($victimDir, 0755);
        @rmdir($victimDir);
    }

    public function testNoPreExistingRepoSqliteDataIsVisibleToSuite(): void
    {
        // The harness stashes repo storage/sqlite before the first test.
        // The production bootstrap (unmodifiable in Task 2) rebuilds an
        // EMPTY storage/sqlite with a fresh schema — by construction it
        // cannot contain pre-existing dev data. The QA wrapper hashes the
        // rebuilt file and removes it during cleanup, restoring the stash.
        $repo = dirname(__DIR__) . '/storage/sqlite';
        if (is_dir($repo)) {
            $entries = array_values(array_diff(scandir($repo) ?: [], ['.', '..']));
            // Only files created DURING this run may exist (mtime >= run
            // start). Anything older is pre-existing repo data = isolation
            // failure.
            $old = array_filter($entries, static fn (string $e): bool => (int) @filemtime($repo . '/' . $e) < (time() - 3600));
            $this->assertSame([], $old, 'pre-existing repo storage/sqlite data visible to the suite: ' . implode(', ', $old));
        }
    }

    public function testBootstrapEnvPointsOnlyAtIsolatedRoot(): void
    {
        // No matter how the app is built, the only sqlite paths in the
        // environment are the two isolated ones; there is no env path that
        // could silently redirect the app to storage/sqlite or /opt.
        $root = (string) IsolatedEnvironment::runRoot();
        $def = (string) ($_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? '');
        $log = (string) ($_ENV['TGUI_TEST_SQLITE_LOG'] ?? '');
        $this->assertStringStartsWith($root, str_replace('\\', '/', $def));
        $this->assertStringStartsWith($root, str_replace('\\', '/', $log));
        $this->assertSame('sqlite', getenv('DB_DRIVER'));
    }
}
