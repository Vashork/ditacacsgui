<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;

/**
 * Production-default proof for the bootstrap's sqlite seam: the test-only
 * path resolution is gated on APP_ENV=testing. Outside that context the
 * bootstrap must select its ORIGINAL repo storage paths (the pre-existing
 * dev-smoke behavior) and ignore the TGUI_TEST_* env vars - i.e. the seam
 * adds no generally supported runtime configuration knob.
 *
 * The default branch (mkdir + touch of the repo files) is exercised in a
 * child process pointed at a byte-identical COPY of the bootstrap layout
 * under the OS temp dir, so the real repository is never written.
 */
final class BootstrapDefaultsTest extends TestCase
{
    public function testProductionDefaultsAreSelectedWithoutTestingGate(): void
    {
        $apiRoot = dirname(__DIR__);
        $real = $apiRoot . '/bootstrap/app.php';
        $src = (string) file_get_contents($real);

        // 1. Structural proof: the seam is gated on APP_ENV=testing and the
        //    repo paths remain the non-testing branch. (Needles use plain
        //    concatenation because the bootstrap file is CRLF-normalized in
        //        the worktree.)
        $q = '[' . chr(39) . chr(34) . ']';
        $this->assertMatchesRegularExpression('/APP_ENV' . $q . '\s*\]\s*\?\?\s*' . $q . $q . '/', $src, 'the seam must read APP_ENV from $_ENV');
        $this->assertMatchesRegularExpression('/===\s*' . $q . 'testing' . $q . '/', $src, 'the seam must be gated on APP_ENV=testing');
        $this->assertMatchesRegularExpression('|\.\./storage/sqlite|', $src, 'the production default repo path must remain the non-testing branch');

        // 2. Behavioral proof in a child process: a byte-identical copy of
        //    the bootstrap, run WITHOUT APP_ENV=testing (production context),
        //    must create its sqlite files at its OWN repo-relative default
        //    path (under the copy, never the real repo) and ignore the
        //    TGUI_TEST_* env vars entirely.
        $copyRoot = sys_get_temp_dir() . '/tgui-defaults-' . bin2hex(random_bytes(4));
        $work = $copyRoot . '/api';
        $this->setUpCopy($apiRoot, $work);

        $script = $work . '/defaults-probe.php';
        file_put_contents($script, $this->probeCode());

        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($work) . ' ' . escapeshellarg($apiRoot);
        exec($cmd . ' 2>&1', $out, $code);
        $out = implode("\n", $out);
        $this->assertSame(0, $code, "child probe failed (expected the production default branch to succeed): " . substr($out, 0, 500));

        // The default branch must have created the two repo-relative files
        // UNDER THE COPY, proving the production default selection, while
        // the real repo storage stays untouched. The bootstrap's __DIR__ is
        // the copy's bootstrap dir, so ../storage/sqlite resolves to
        // <work>/storage/sqlite (the copy's own storage dir).
        $this->assertFileExists($work . '/storage/sqlite/tgui.sqlite', 'default branch must create the repo-relative sqlite file under the copy');
        $this->assertFileExists($work . '/storage/sqlite/tgui_log.sqlite', 'default branch must create the repo-relative log file under the copy');
        $this->assertFalse(is_dir($apiRoot . '/storage/sqlite'), 'the real repo storage/sqlite must not be created by the probe');
        $this->assertFileDoesNotExist($work . '/should-be-ignored.sqlite', 'TGUI_TEST_SQLITE_DEFAULT must be ignored outside APP_ENV=testing');

        // Cleanup the copy tree (temp only).
        IsolatedEnvironment::removeTree($copyRoot);
    }

    /**
     * Copy the minimum bootstrap layout (byte-identical) into $work so the
     * child process's `__DIR__`-relative paths resolve under the temp copy.
     */
    private function setUpCopy(string $apiRoot, string $work): void
    {
        foreach ([$work, $work . '/bootstrap', $work . '/app', $work . '/app/Validation'] as $d) {
            $this->assertTrue(mkdir($d, 0777, true), "cannot create {$d}");
        }
        $real = $apiRoot . '/bootstrap/app.php';
        self::copyFile($real, $work . '/bootstrap/app.php');
        $this->assertSame(hash_file('sha256', $real), hash_file('sha256', $work . '/bootstrap/app.php'), 'the copy must be byte-identical to the worktree bootstrap');
        self::copyFile($apiRoot . '/constants.php', $work . '/constants.php');
        foreach (glob($apiRoot . '/app/*.php') ?: [] as $f) {
            self::copyFile($f, $work . '/app/' . basename($f));
        }
        foreach (glob($apiRoot . '/app/Validation/*.php') ?: [] as $f) {
            self::copyFile($f, $work . '/app/Validation/' . basename($f));
        }
        // Vendor: the child requires the REAL autoload by absolute path
        // (read-only use of the repo's vendor; nothing is written there).
    }

    private static function copyFile(string $from, string $to): void
    {
        $dir = dirname($to);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!copy($from, $to)) {
            throw new \RuntimeException("cannot copy {$from} -> {$to}");
        }
    }

    private function probeCode(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);
// Child probe: PRODUCTION context - no APP_ENV=testing. The TGUI_TEST_*
// vars ARE set to temp paths that would satisfy the gate if it existed;
// the bootstrap must ignore them and use its default repo-relative paths.
$work = $argv[1];
$api = $argv[2];
$_ENV['DB_DRIVER'] = 'sqlite'; $_SERVER['DB_DRIVER'] = 'sqlite';
// TAC/HA path vars (isolated, nonexistent) so constants.php never points at
// /opt on this machine (production code path, same as the test harness).
$_ENV['TAC_ROOT_PATH'] = $work . '/tacacsgui'; $_SERVER['TAC_ROOT_PATH'] = $_ENV['TAC_ROOT_PATH'];
$_ENV['HA_SETTINGS_PATH'] = $work . '/ha/ha-settings.yaml'; $_SERVER['HA_SETTINGS_PATH'] = $_ENV['HA_SETTINGS_PATH'];
$_ENV['HA_MASTER_PATH'] = $work . '/ha/ha-master.yaml'; $_SERVER['HA_MASTER_PATH'] = $_ENV['HA_MASTER_PATH'];
$_ENV['HA_SLAVES_PATH'] = $work . '/ha/ha-slaves.yaml'; $_SERVER['HA_SLAVES_PATH'] = $_ENV['HA_SLAVES_PATH'];
putenv('TAC_ROOT_PATH=' . $work . '/tacacsgui');
putenv('HA_SETTINGS_PATH=' . $work . '/ha/ha-settings.yaml');
putenv('HA_MASTER_PATH=' . $work . '/ha/ha-master.yaml');
putenv('HA_SLAVES_PATH=' . $work . '/ha/ha-slaves.yaml');
// Deliberately NOT setting APP_ENV=testing (production context).
$_ENV['TGUI_TEST_ROOT'] = $work; $_SERVER['TGUI_TEST_ROOT'] = $work;
$_ENV['TGUI_TEST_SQLITE_DEFAULT'] = $work . '/should-be-ignored.sqlite';
$_SERVER['TGUI_TEST_SQLITE_DEFAULT'] = $_ENV['TGUI_TEST_SQLITE_DEFAULT'];
$_ENV['TGUI_TEST_SQLITE_LOG'] = $work . '/should-be-ignored-log.sqlite';
$_SERVER['TGUI_TEST_SQLITE_LOG'] = $_ENV['TGUI_TEST_SQLITE_LOG'];
putenv('DB_DRIVER=sqlite');
putenv('TGUI_TEST_ROOT=' . $work);
putenv('TGUI_TEST_SQLITE_DEFAULT=' . $work . '/should-be-ignored.sqlite');
putenv('TGUI_TEST_SQLITE_LOG=' . $work . '/should-be-ignored-log.sqlite');
require $api . '/vendor/autoload.php';
$app = require $work . '/bootstrap/app.php';
$db = $app->getContainer()->get('db');
$pdo = $db->connection('default')->getPdo();
$file = str_replace('\\', '/', (string) ($pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'] ?? ''));
// PHP's __DIR__ . '/../storage/sqlite' resolves to the COPY's own
// storage dir (the copy's api dir + ../ = copyRoot, so the file lands at
// <copyRoot>/api/../storage/sqlite - i.e. under the copy, never the repo).
$expected = str_replace('\\', '/', $work . '/storage/sqlite/tgui.sqlite');
if ($file !== $expected) {
    fwrite(STDERR, "PROBE-ERROR: default branch selected the wrong file: {$file} (expected {$expected})\n");
    exit(5);
}
fwrite(STDERR, "OK default file: {$file}\n");
exit(0);
PHP;
    }
}
