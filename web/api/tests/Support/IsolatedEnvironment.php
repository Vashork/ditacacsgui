<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use RuntimeException;

/**
 * Per-run isolated environment for integration tests (Task 2 harness).
 *
 * Responsibilities:
 *  - create a unique temp root under the OS temp dir (fail-closed, with
 *    bounded retries for 9p transient errors);
 *  - point native sessions at a unique temp save_path with a unique
 *    session.name / session.id;
 *  - expose the test-only JWT secret constant (known, non-placeholder);
 *  - expose the run context (root path + env snapshot) to tests;
 *  - provide a trap-safe recursive tree removal (retries + unlink errors
 *    swallowed only when the path no longer exists).
 */
final class IsolatedEnvironment
{
    /**
     * Known test-only JWT secret. Deliberately fixed and non-placeholder:
     * tests 4-8 must be able to mint/verify tokens deterministically.
     * This value is NOT a credential; it only signs test-process tokens.
     */
    public const TEST_JWT_SECRET = 'tgui-phpunit-test-secret-0123456789abcdef-DO-NOT-USE-IN-PROD';

    /** OS temp dir, resolved once. */
    private static ?string $osTempDir = null;

    /** Temp root for this process. */
    private static ?string $runRoot = null;

    /** EnvSnapshot taken at bootstrap. */
    private static ?EnvSnapshot $snapshot = null;

    /** Unique session name used for this run. */
    private static ?string $sessionName = null;

    public static function osTempDir(): string
    {
        if (self::$osTempDir === null) {
            $dir = (string) (getenv('TEMP') ?: getenv('TMP') ?: sys_get_temp_dir());
            $dir = rtrim(str_replace('\\', '/', $dir), '/');
            if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
                throw new RuntimeException('OS temp directory is missing or not writable: ' . var_export($dir, true));
            }
            self::$osTempDir = $dir;
        }
        return self::$osTempDir;
    }

    /**
     * Create the per-run temp root. Throws (fail-closed) when it cannot be
     * created after $retries attempts.
     */
    public static function createTempRoot(string $prefix, int $retries = 1): string
    {
        $dir = self::osTempDir() . '/' . $prefix;
        $lastError = '';
        for ($attempt = 1; $attempt <= max(1, $retries); $attempt++) {
            if (is_dir($dir)) {
                // Collision is effectively impossible (random+pid); treat as
                // a stale root from a crashed run and reuse it after clearing.
                self::removeTree($dir);
            }
            $ok = @mkdir($dir, 0700, true);
            if ($ok && is_dir($dir) && is_writable($dir)) {
                self::$runRoot = $dir;
                return $dir;
            }
            $lastError = error_get_last()['message'] ?? 'unknown mkdir failure';
            clear_error_handler();
            usleep(50000 * $attempt);
        }
        throw new RuntimeException('Cannot create isolated temp root ' . $dir . ' (last error: ' . $lastError . ')');
    }

    /** Run context root; null before bootstrap completes. */
    public static function runRoot(): ?string
    {
        return self::$runRoot;
    }

    public static function setRunContext(string $runRoot, EnvSnapshot $snapshot): void
    {
        self::$runRoot = $runRoot;
        self::$snapshot = $snapshot;
    }

    public static function snapshot(): EnvSnapshot
    {
        if (self::$snapshot === null) {
            throw new RuntimeException('IsolatedEnvironment::setRunContext() was not called (tests/bootstrap.php missing?).');
        }
        return self::$snapshot;
    }

    /**
     * Configure native sessions for the test process: unique name/id/save
     * path under the run root. Fails closed when the save path cannot be
     * created or written.
     */
    public static function configureTestSession(string $runRoot): void
    {
        $savePath = $runRoot . '/sessions';
        if (!is_dir($savePath) && !@mkdir($savePath, 0700, true)) {
            throw new RuntimeException('Cannot create isolated session save path ' . $savePath);
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
    }

    public static function sessionName(): string
    {
        if (self::$sessionName === null) {
            throw new RuntimeException('Session not configured yet.');
        }
        return self::$sessionName;
    }

    /**
     * Trap-safe recursive removal of $dir. Retries transiently; never throws
     * (teardown must not mask the original test outcome).
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
            if ($item->isDir() && str_ends_with($p, '.git')) {
                continue;
            }
            if ($item->isDir()) {
                @rmdir($p);
            } else {
                @unlink($p);
            }
        }
        for ($i = 0; $i < 5 && is_dir($dir); $i++) {
            usleep(20000);
            @rmdir($dir);
        }
    }

    /**
     * Ensure $_SESSION exists before any capture/restore (it is an
     * uninitialized global until the first session_start / first use).
     */
    private static function ensureSessionGlobal(): void
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    /**
     * Snapshot + restore helper used by tests to guarantee no env/global
     * leak between cases (static lockout state, $_SESSION, $_ENV, superglobals
     * touched by controllers). Call restore() from a finally block.
     *
     * @return array{
     *   env: array<string,string>,
     *   putenv: array<string,string|null>,
     *   session: array<string,mixed>,
     *   cookies: array<string,mixed>,
     *   ini: array{session.save_path:string, session.name:string}
     * }
     */
    public static function captureSuperglobals(array $extraEnv = []): array
    {
        self::ensureSessionGlobal();
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
            'putenv' => array_combine(
                $envKeys,
                array_map(static fn (string $k): ?string => getenv($k) ?: null, $envKeys)
            ) ?: [],
            'session' => $_SESSION,
            'cookies' => $_COOKIE ?? [],
            'ini' => [
                'session.save_path' => (string) ini_get('session.save_path'),
                'session.name' => (string) ini_get('session.name'),
            ],
        ];
    }

    /** Restore a state captured by captureSuperglobals(). */
    public static function restoreSuperglobals(array $state): void
    {
        self::ensureSessionGlobal();
        // $_SESSION / $_COOKIE: replace wholesale.
        $_SESSION = $state['session'] ?? [];
        $_COOKIE = $state['cookies'] ?? [];

        // INI session settings back to the harness defaults for this run.
        $ini = $state['ini'] ?? [];
        if (isset($ini['session.save_path'])) {
            ini_set('session.save_path', $ini['session.save_path']);
        }
        if (isset($ini['session.name'])) {
            ini_set('session.name', $ini['session.name']);
        }

        // Env: for every tracked key, restore exactly the captured state
        // (absent => unset both $_ENV and putenv; present => set both).
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
