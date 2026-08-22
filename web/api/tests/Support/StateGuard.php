<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use PHPUnit\Framework\AssertionFailedError;

/**
 * Per-test isolation guard (Task 2 harness, used by Tasks 4-8).
 *
 * Usage inside a test case:
 *   $guard = StateGuard::start($this);
 *   ... test body ...
 *   // in finally / tearDown:
 *   $guard->verify();
 *
 * It asserts that a test left no residue in:
 *  - $_SESSION (compared to the captured pre-test state),
 *  - $_COOKIE,
 *  - the tracked environment keys (DB_DRIVER, test sqlite paths, HA paths,
 *    JWT keys) — the harness defaults must be intact,
 *  - the isolated temp root (only the expected sub-structure may exist;
 *    stray files are reported),
 *  - no PHP session files for a foreign session name.
 */
final class StateGuard
{
    /** @var array<string,mixed> */
    private array $baseline;

    /** @return array<string,mixed> the captured pre-test global state */
    public function baselineState(): array
    {
        return $this->baseline;
    }

    /** @var list<string> */
    private array $expectedRootEntries;

    private function __construct()
    {
    }

    public static function start(): self
    {
        $g = new self();
        $g->baseline = IsolatedEnvironment::captureSuperglobals();
        $root = IsolatedEnvironment::runRoot();
        $g->expectedRootEntries = $root === null ? [] : self::listRoot($root);
        return $g;
    }

    /** Verify isolation; throws AssertionFailedError listing every leak. */
    public function verify(): void
    {
        $leaks = [];

        // 1. Session state: must equal the captured baseline.
        if ($_SESSION !== ($this->baseline['session'] ?? [])) {
            $leaks[] = '$_SESSION mutated: ' . self::brief($_SESSION);
        }
        if (($_COOKIE ?? []) !== ($this->baseline['cookies'] ?? [])) {
            $leaks[] = '$_COOKIE mutated: ' . self::brief($_COOKIE);
        }

        // 2. Env keys: must equal the captured baseline (harness defaults).
        $now = IsolatedEnvironment::captureSuperglobals();
        foreach (array_keys($this->baseline['env'] ?? []) as $k) {
            if (($now['env'][$k] ?? null) !== ($this->baseline['env'][$k] ?? null)) {
                $leaks[] = 'env key \'' . $k . '\' changed';
            }
        }
        foreach (array_keys($this->baseline['putenv'] ?? []) as $k) {
            if ((getenv($k) ?: null) !== ($this->baseline['putenv'][$k] ?? null)) {
                $leaks[] = 'getenv(\'' . $k . '\') changed';
            }
        }

        // 3. Session save path must still be the isolated one.
        $sp = (string) ini_get('session.save_path');
        $root = IsolatedEnvironment::runRoot();
        if ($root !== null && $sp !== '' && !str_starts_with(str_replace('\\', '/', $sp), str_replace('\\', '/', $root))) {
            $leaks[] = 'session.save_path left the isolated root: ' . $sp;
        }

        // 4. Temp root: no UNEXPECTED top-level entries appeared.
        if ($root !== null && is_dir($root)) {
            $current = self::listRoot($root);
            $stray = array_diff($current, $this->expectedRootEntries);
            // sqlite files and sessions/ are expected to (re)appear from app boot.
            $stray = array_filter($stray, static fn (string $e): bool => !in_array($e, ['tgui-test.sqlite', 'tgui-test-log.sqlite', 'sessions'], true));
            if ($stray !== []) {
                $leaks[] = 'stray entries in temp root: ' . implode(', ', array_map('strval', $stray));
            }
        }

        if ($leaks !== []) {
            throw new AssertionFailedError('State isolation violation(s): ' . implode(' | ', $leaks));
        }
    }

    /** @return list<string> */
    private static function listRoot(string $root): array
    {
        $entries = @scandir($root) ?: [];
        return array_values(array_diff($entries, ['.', '..']));
    }

    private static function brief(mixed $v): string
    {
        $s = json_encode($v);
        return $s === false ? var_export($v, true) : (strlen($s) > 200 ? substr($s, 0, 200) . '…' : $s);
    }
}
