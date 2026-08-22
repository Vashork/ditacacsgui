<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;

/**
 * The production bootstrap's sqlite seam (APP_ENV=testing gate in
 * bootstrap/app.php) must fail closed - never silently fall back to the
 * repo storage path, never to MySQL - when the injected test paths are
 * malformed or escape the isolated root.
 *
 * The production gate lives inside the bootstrap's `db` closure, so each
 * probe requires the REAL bootstrap/app.php in a CHILD process (the
 * bootstrap is a script and cannot be re-required in the suite process
 * after the suite has already booted it), then forces the db binding to
 * run. A child that exits 0 would mean the seam fell through to the repo
 * storage path - the worst possible outcome.
 */
final class BootstrapSeamFailClosedTest extends TestCase
{
    private string $probeScript;
    private string $probeDir;

    protected function setUp(): void
    {
        $this->probeDir = sys_get_temp_dir() . '/tgui-seam-probe-' . bin2hex(random_bytes(4));
        if (!mkdir($this->probeDir, 0777, true)) {
            $this->fail('cannot create probe dir ' . $this->probeDir);
        }
        $this->probeScript = $this->probeDir . '/seam-probe.php';
        file_put_contents($this->probeScript, $this->probeCode());
    }

    protected function tearDown(): void
    {
        IsolatedEnvironment::removeTree($this->probeDir);
    }

    public function testMalformedOrOutOfRootOverridesFailClosed(): void
    {
        $runRoot = IsolatedEnvironment::runRoot();
        $goodDef = (string) $_ENV['TGUI_TEST_SQLITE_DEFAULT'];
        $goodLog = (string) $_ENV['TGUI_TEST_SQLITE_LOG'];
        $outsideRoot = $runRoot . '-outside';
        mkdir($outsideRoot, 0777, true);

        // [TGUI_TEST_ROOT, TGUI_TEST_SQLITE_DEFAULT, TGUI_TEST_SQLITE_LOG, expected message part]
        $probes = [
            [$runRoot, '', $goodLog, 'must be distinct files inside TGUI_TEST_ROOT'],
            [$runRoot, $goodDef, $goodDef, 'must be distinct files inside TGUI_TEST_ROOT'],
            [$runRoot . '/sub', $goodDef, $goodLog, 'must be distinct files inside TGUI_TEST_ROOT'],
            [$outsideRoot, $runRoot . '/esc-def.sqlite', $runRoot . '/esc-log.sqlite', 'must be distinct files inside TGUI_TEST_ROOT'],
            [$runRoot, 'NUL-BYTE', $goodLog, 'must be distinct files inside TGUI_TEST_ROOT'],
            [$runRoot, $runRoot . '/absent-def.sqlite', $goodLog, 'missing or not writable'],
        ];

        $apiRoot = dirname(__DIR__);
        $i = 0;
        foreach ($probes as [$root, $def, $log, $expected]) {
            $i++;
            $defArg = ($def === 'NUL-BYTE') ? $runRoot . '/x' : $def;
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->probeScript)
                . ' ' . escapeshellarg($apiRoot)
                . ' ' . escapeshellarg($root)
                . ' ' . escapeshellarg($defArg)
                . ' ' . escapeshellarg($log);
            // The NUL-byte probe: the child re-applies the NUL byte (which
            // cannot cross a process argument boundary) from this marker.
            if ($def === 'NUL-BYTE') {
                $cmd .= ' --nul';
            }
            exec($cmd . ' 2>&1', $out, $code);
            $out = implode("\n", $out);
            $this->assertNotSame(0, $code, "probe {$i}: child must fail (no silent repo fallback), out: " . substr($out, 0, 400));
            $this->assertStringContainsString('RuntimeException', $out, "probe {$i}: controlled RuntimeException expected, out: " . substr($out, 0, 400));
            $this->assertStringContainsString($expected, $out, "probe {$i}: expected message, out: " . substr($out, 0, 400));
            $this->assertStringContainsString('no MySQL fallback', $out, "probe {$i}: message must state no MySQL fallback, out: " . substr($out, 0, 400));
            // The repo storage dir must remain untouched by the probe.
            $this->assertFalse(is_dir($apiRoot . '/storage/sqlite'), "probe {$i}: repo storage/sqlite must not be created, out: " . substr($out, 0, 400));
        }

        IsolatedEnvironment::removeTree($outsideRoot);
    }

    private function probeCode(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);
// Child probe: set the seam env vars, require the REAL production bootstrap,
// force the db binding to run (capsule build), and exit non-zero on the
// expected controlled error. A silent success would mean the seam fell
// through to the repo storage path - the worst possible outcome.
$api = $argv[1]; $root = $argv[2]; $def = $argv[3]; $log = $argv[4];
if (in_array('--nul', $argv, true)) {
    $def = $def . "\0" . '.sqlite'; // re-apply the NUL byte (cannot cross argv)
}
$_ENV['DB_DRIVER'] = 'sqlite'; $_SERVER['DB_DRIVER'] = 'sqlite';
$_ENV['APP_ENV'] = 'testing'; $_SERVER['APP_ENV'] = 'testing';
$_ENV['TGUI_TEST_ROOT'] = $root; $_SERVER['TGUI_TEST_ROOT'] = $root;
$_ENV['TGUI_TEST_SQLITE_DEFAULT'] = $def; $_SERVER['TGUI_TEST_SQLITE_DEFAULT'] = $def;
$_ENV['TGUI_TEST_SQLITE_LOG'] = $log; $_SERVER['TGUI_TEST_SQLITE_LOG'] = $log;
putenv('DB_DRIVER=sqlite'); putenv('APP_ENV=testing'); putenv('TGUI_TEST_ROOT=' . $root);
putenv('TGUI_TEST_SQLITE_LOG=' . $log);
$_ENV['TAC_ROOT_PATH'] = $root . '/tacacsgui'; $_SERVER['TAC_ROOT_PATH'] = $_ENV['TAC_ROOT_PATH'];
$_ENV['HA_SETTINGS_PATH'] = $root . '/ha/ha-settings.yaml'; $_SERVER['HA_SETTINGS_PATH'] = $_ENV['HA_SETTINGS_PATH'];
$_ENV['HA_MASTER_PATH'] = $root . '/ha/ha-master.yaml'; $_SERVER['HA_MASTER_PATH'] = $_ENV['HA_MASTER_PATH'];
$_ENV['HA_SLAVES_PATH'] = $root . '/ha/ha-slaves.yaml'; $_SERVER['HA_SLAVES_PATH'] = $_ENV['HA_SLAVES_PATH'];
putenv('TAC_ROOT_PATH=' . $root . '/tacacsgui');
putenv('HA_SETTINGS_PATH=' . $root . '/ha/ha-settings.yaml');
putenv('HA_MASTER_PATH=' . $root . '/ha/ha-master.yaml');
putenv('HA_SLAVES_PATH=' . $root . '/ha/ha-slaves.yaml');
require $api . '/vendor/autoload.php';
try {
    $app = require $api . '/bootstrap/app.php';
    $app->getContainer()->get('db'); // force the db closure to run
    fwrite(STDERR, "PROBE-ERROR: bootstrap succeeded - seam fell through (repo storage would be written)\n");
    exit(3);
} catch (\Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(4);
}
PHP;
    }
}
