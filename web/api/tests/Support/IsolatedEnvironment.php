<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use RuntimeException;

/**
 * Per-run isolated environment for integration tests (Task 2 harness).
 *
 * Responsibilities (OS-temp only - this class never references a repo path):
 *  - create a unique temp root under the OS temp dir (fail-closed);
 *  - point native sessions at a unique temp save_path with a unique name;
 *  - expose the known test-only JWT secret (non-placeholder constant);
 *  - capture/restore the env+superglobals a test case may touch, so no
 *    state leaks between cases;
 *  - trap-safe recursive tree removal for teardown.
 */
final class IsolatedEnvironment
{
    /**
     * Known test-only JWT secret. Fixed and non-placeholder so tests can
     * mint/verify tokens deterministically. NOT a credential: it only signs
     * test-process tokens and is never used in production.
     */
    public const TEST_JWT_SECRET = 'tgui-phpunit-test-secret-0123456789abcdef-DO-NOT-USE-IN-PROD';

    private static ?string $runRoot = null;
    private static ?string $sessionName = null;
    private static ?EnvSnapshot $snapshot = null;

    /** @return string the per-process run root (throws before bootstrap) */
    public static function runRoot(): string
    {
        if (self::$runRoot === null) {
            throw new RuntimeException('IsolatedEnvironment::runRoot() called before tests/bootstrap.php set it.');
        }
        return self::$runRoot;
    }

    public static function setRunContext(string $runRoot, ?EnvSnapshot $snapshot = null): void
    {
        self::$runRoot = $runRoot;
        self::$snapshot = $snapshot;
    }

    /** @return ?EnvSnapshot the bootstrap's env snapshot (null before bootstrap) */
    public static function snapshot(): ?EnvSnapshot
    {
        return self::$snapshot;
    }

    /**
     * Create the per-run temp root under the OS temp dir. Fails closed
     * after $retries attempts (9p can transiently fail with ENOSPC).
     *
     * @param string $prefix directory name prefix (run id appended by caller)
     * @param int $retries bounded retry count
     * @return string the created directory (absolute, forward slashes)
     * @throws RuntimeException when creation is impossible
     */
    public static function createTempRoot(string $prefix, int $retries = 3): string
    {
        $base = (string) (getenv('TEMP') ?: getenv('TMP') ?: sys_get_temp_dir());
        $base = rtrim(str_replace('\\', '/', $base), '/');
        if ($base === '' || !is_dir($base) || !is_writable($base)) {
            throw new RuntimeException('OS temp directory is missing or not writable: ' . var_export($base, true));
        }
        $dir = $base . '/' . $prefix;
        $lastError = 'unknown';
        for ($attempt = 1; $attempt <= max(1, $retries); $attempt++) {
            if (is_dir($dir)) {
                self::removeTree($dir); // stale root from a crashed run (random+pid name)
            }
            if (@mkdir($dir, 0700, true) && is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
            $lastError = error_get_last()['message'] ?? 'unknown';
            usleep(50000 * $attempt);
        }
        throw new RuntimeException('Cannot create isolated temp root ' . $dir . ' (last error: ' . $lastError . ')');
    }

    /**
     * Configure native sessions for the test process: unique name + save
     * path inside the run root. Fails closed when the save path cannot be
     * created or is not writable.
     *
     * @param string $runRoot per-run temp root
     * @return string the session name assigned to this process
     * @throws RuntimeException when the save path is unusable
     */
    public static function configureTestSession(string $runRoot): string
    {
        $savePath = $runRoot . '/sessions';
        if (!is_dir($savePath) && !@mkdir($savePath, 0700, true)) {
            throw new RuntimeException('Cannot create isolated session save path: ' . $savePath);
        }
        if (!is_writable($savePath)) {
            throw new RuntimeException('Isolated session save path is not writable: ' . $savePath);
        }
        $name = 'tguitest' . bin2hex(random_bytes(4));
        ini_set('session.save_handler', 'files');
        ini_set('session.save_path', str_replace('\\', '/', $savePath));
        ini_set('session.name', $name);
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.sid_length', '32');
        ini_set('session.lazy_write', '1');
        self::$sessionName = $name;
        return $name;
    }

    /** @return string the session name for this process */
    public static function sessionName(): string
    {
        if (self::$sessionName === null) {
            throw new RuntimeException('Session not configured yet.');
        }
        return self::$sessionName;
    }

    /**
     * Trap-safe recursive removal of $dir. Never throws (teardown must not
     * mask the original test outcome). Retries the final rmdir briefly
     * because 9p can lag on unlink.
     *
     * @param string $dir directory to remove
     * @return void
     */
    public static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $p = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($p);
            } else {
                @unlink($p);
            }
        }
        for ($i = 0; $i < 10 && is_dir($dir); $i++) {
            usleep(50000);
            @rmdir($dir);
        }
    }

    /**
     * Snapshot of the globals a test case may touch (env keys, $_SESSION,
     * $_COOKIE, session ini). Use with restoreSuperglobals() in a finally.
     *
     * @param list<string> $extraEnv extra env keys under management
     * @return array<string,mixed> captured state
     */
    public static function captureSuperglobals(array $extraEnv = []): array
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $envKeys = array_values(array_unique(array_merge([
            'DB_DRIVER',
            'TGUI_TEST_SQLITE_DEFAULT',
            'TGUI_TEST_SQLITE_LOG',
            'HA_SETTINGS_PATH',
            'HA_MASTER_PATH',
            'HA_SLAVES_PATH',
            'TAC_ROOT_PATH',
            'JWT_SECRET',
            'JWT_ALGORITHM',
        ], $extraEnv)));
        return [
            'env' => array_intersect_key($_ENV, array_flip($envKeys)),
            'putenv' => array_combine($envKeys, array_map(static fn (string $k): ?string => getenv($k) ?: null, $envKeys)),
            'session' => $_SESSION,
            'cookies' => $_COOKIE ?? [],
            'ini' => [
                'session.save_path' => (string) ini_get('session.save_path'),
                'session.name' => (string) ini_get('session.name'),
            ],
        ];
    }

    /**
     * Restore a state captured by captureSuperglobals(): session/cookies
     * wholesale, tracked env keys to their captured values, session ini
     * back to the harness defaults.
     *
     * @param array<string,mixed> $state from captureSuperglobals()
     * @return void
     */
    public static function restoreSuperglobals(array $state): void
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION = $state['session'] ?? [];
        $_COOKIE = $state['cookies'] ?? [];
        $ini = $state['ini'] ?? [];
        if (isset($ini['session.save_path'])) {
            ini_set('session.save_path', $ini['session.save_path']);
        }
        if (isset($ini['session.name'])) {
            ini_set('session.name', $ini['session.name']);
        }
        $tracked = array_unique(array_merge(array_keys($state['env'] ?? []), array_keys($state['putenv'] ?? [])));
        foreach ($tracked as $k) {
            if (array_key_exists($k, $state['env'] ?? [])) {
                $_ENV[$k] = $state['env'][$k];
                putenv($k . '=' . $state['env'][$k]);
            } elseif (array_key_exists($k, $state['putenv'] ?? []) && $state['putenv'][$k] !== null) {
                putenv($k . '=' . $state['putenv'][$k]);
                unset($_ENV[$k]);
            } else {
                unset($_ENV[$k]);
                putenv($k);
            }
        }
    }
}
