<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use RuntimeException;

/**
 * Isolated environment management for the Task 2 integration harness.
 *
 * All filesystem activity of the harness happens under ONE per-run OS-temp
 * root (created via IsolatedEnvironment::createTempRoot). This class never
 * touches repository paths.
 */
final class IsolatedEnvironment
{
    /**
     * Known, non-credential test-only JWT secret. It is a placeholder value
     * with no relationship to any deployment's real secret.
     */
    public const TEST_JWT_SECRET = 'tgui-phpunit-test-secret-not-a-credential';

    private static ?string $runRoot = null;
    private static ?EnvSnapshot $envSnapshot = null;

    /**
     * Create the per-run temp root under the OS temp dir, sweeping any
     * stale harness roots first. A hard kill of a previous run leaves its
     * root behind (the shutdown hook cannot run after SIGKILL) - the NEXT
     * run's sweep is the only cleanup; that is the documented, expected
     * behavior.
     *
     * @throws RuntimeException fail-closed on any creation problem
     */
    public static function createTempRoot(string $prefix, int $attempts = 5): string
    {
        self::sweepStaleRoots($prefix);

        $base = (string) sys_get_temp_dir();
        for ($i = 0; $i < max(1, $attempts); $i++) {
            $name = $prefix . '-' . bin2hex(random_bytes(4));
            $path = $base . DIRECTORY_SEPARATOR . $name;
            if (@mkdir($path, 0777, true)) {
                if (is_dir($path) && is_writable($path)) {
                    return $path;
                }
                @rmdir($path);
            }
            // 9p / network temp dirs can lag on unlink; retry briefly.
            usleep(50000);
        }
        throw new RuntimeException('IsolatedEnvironment: cannot create temp root under ' . $base);
    }

    /** Remove leftover roots of the same harness from previously killed runs. */
    public static function sweepStaleRoots(string $prefix): void
    {
        $base = (string) sys_get_temp_dir();
        if (!is_dir($base)) {
            return;
        }
        foreach (new \DirectoryIterator($base) as $it) {
            if (!$it->isDir() || !str_starts_with((string) $it->getFilename(), $prefix . '-')) {
                continue;
            }
            if ($it->getFilename() === (string) basename((string) self::$runRoot)) {
                continue; // never sweep the live run root
            }
            self::removeTree($it->getPathname());
        }
    }

    public static function setRunContext(string $runRoot, ?EnvSnapshot $snapshot = null): void
    {
        self::$runRoot = $runRoot;
        if ($snapshot !== null) {
            self::$envSnapshot = $snapshot;
        }
    }

    /** @throws RuntimeException when no run context has been established */
    public static function runRoot(): string
    {
        if (self::$runRoot === null) {
            throw new RuntimeException('IsolatedEnvironment: no run context (bootstrap not run?).');
        }
        return self::$runRoot;
    }

    /** @return EnvSnapshot|null the pre-test env snapshot (if captured) */
    public static function envSnapshot(): ?EnvSnapshot
    {
        return self::$envSnapshot;
    }

    /**
     * Capture the superglobal + env state for per-test isolation guards
     * ($_SESSION, $_COOKIE, and every key under EnvSnapshot management).
     *
     * @return array{0:array<string,mixed>, 1:array<string,mixed>}
     *         [$baselineState (for restoreSuperglobals), $envState (for verify)]
     */
    public static function captureSuperglobals(): array
    {
        $env = self::$envSnapshot !== null ? self::$envSnapshot->state() : [];
        return [
            ['_SESSION' => $_SESSION, '_COOKIE' => $_COOKIE],
            ['env' => $env['env'] ?? [], 'putenv' => $env['putenv'] ?? []],
        ];
    }

    /**
     * Configure native sessions into the isolated root: unique session name
     * (no cross-process cookie collision) and a temp save path.
     */
    public static function configureTestSession(string $runRoot): void
    {
        $sessionDir = $runRoot . DIRECTORY_SEPARATOR . 'sessions';
        if (!is_dir($sessionDir) && !@mkdir($sessionDir, 0777, true)) {
            throw new RuntimeException('IsolatedEnvironment: cannot create session dir ' . $sessionDir);
        }
        ini_set('session.save_path', $sessionDir);
        ini_set('session.name', 'tguitest' . bin2hex(random_bytes(4)));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.lazy_write', '1');
    }

    /**
     * Restore the pre-test superglobal state (session/cookie arrays + the
     * snapshot's tracked env vars). Env keys are restored FIRST and then
     * re-applied to the harness defaults below: the per-case snapshot
     * captures the harness defaults (not the pre-process values), so a
     * test that changed a tracked env key must see the harness default
     * restored, and the harness defaults are exactly what the next case's
     * baseline will capture.
     */
    public static function restoreSuperglobals(array $baseline): void
    {
        $snapshot = self::$envSnapshot;
        if ($snapshot !== null) {
            $snapshot->restore();
        }
        if (array_key_exists('_SESSION', $baseline)) {
            $_SESSION = $baseline['_SESSION'];
        } else {
            $_SESSION = [];
        }
        if (array_key_exists('_COOKIE', $baseline)) {
            $_COOKIE = $baseline['_COOKIE'];
        } else {
            $_COOKIE = [];
        }
        // Re-assert the harness defaults so the next case starts from the
        // same environment the suite bootstrap established.
        if ($snapshot !== null) {
            $snapshot->apply([
                'DB_DRIVER' => 'sqlite',
                'APP_ENV' => 'testing',
                'TGUI_TEST_ROOT' => (string) self::$runRoot,
                'TGUI_TEST_SQLITE_DEFAULT' => (string) self::$runRoot . '/tgui-test.sqlite',
                'TGUI_TEST_SQLITE_LOG' => (string) self::$runRoot . '/tgui-test-log.sqlite',
                'HA_SETTINGS_PATH' => (string) self::$runRoot . '/ha/ha-settings.yaml',
                'HA_MASTER_PATH' => (string) self::$runRoot . '/ha/ha-master.yaml',
                'HA_SLAVES_PATH' => (string) self::$runRoot . '/ha/ha-slaves.yaml',
                'TAC_ROOT_PATH' => (string) self::$runRoot . '/tacacsgui',
                'JWT_SECRET' => self::TEST_JWT_SECRET,
                'JWT_ALGORITHM' => 'HS256',
            ]);
        }
    }

    /**
     * Recursively remove a tree. Bounded: only used on harness temp roots.
     * The harness's temp roots live in the OS temp dir; network (9p) temp
     * dirs can lag on unlink, so each removal is retried briefly.
     */
    public static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        for ($i = 0; $i < 5 && is_dir($path); $i++) {
            if (@rmdir($path)) {
                return;
            }
            usleep(50000);
        }
    }
}
