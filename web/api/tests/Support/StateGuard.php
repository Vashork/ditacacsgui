<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

/**
 * Per-test isolation guard (Task 2 harness, reused by Tasks 4-8).
 *
 * Usage:
 *   $guard = StateGuard::start();            // in setUp
 *   ... test body ...
 *   IsolatedEnvironment::restoreSuperglobals($guard->baselineState()); // in finally
 *   $guard->verify();                        // in tearDown (after restore)
 *
 * verify() fails the test when a case left residue in:
 *  - the isolated temp root (stray top-level entries),
 *  - the session save path (files for the unique test session name),
 *  - the tracked environment keys (harness defaults must be intact).
 */
final class StateGuard
{
    /** @var array<string,mixed> */
    private array $baseline;

    /** @var array{env:array<string,string>,putenv:array<string,string>} */
    private array $baselineEnv;

    /** @var list<string> */
    private array $expectedRootEntries;

    private function __construct()
    {
    }

    /** @return StateGuard with the pre-test state captured */
    public static function start(): self
    {
        $g = new self();
        [$g->baseline, $g->baselineEnv] = IsolatedEnvironment::captureSuperglobals();
        $root = IsolatedEnvironment::runRoot();
        $g->expectedRootEntries = self::listRoot($root);
        return $g;
    }

    /** @return array<string,mixed> the captured pre-test global state */
    public function baselineState(): array
    {
        return $this->baseline;
    }

    /**
     * Verify isolation; throws AssertionFailedError listing every leak.
     * Call AFTER restoreSuperglobals() so session/cookie equality holds by
     * construction and the guard checks the durable artifacts.
     *
     * @throws AssertionFailedError on any detected leak
     */
    public function verify(): void
    {
        $leaks = [];

        // 1. Tracked env keys must equal the captured baseline (harness
        //    defaults intact - a test that changed them and did not restore
        //    is a leak).
        foreach (array_keys($this->baselineEnv['env']) as $k) {
            if (($_ENV[$k] ?? null) !== $this->baselineEnv['env'][$k]) {
                $leaks[] = 'env key \'' . $k . '\' changed';
            }
        }
        foreach (array_keys($this->baselineEnv['putenv']) as $k) {
            if ((getenv($k) ?: null) !== $this->baselineEnv['putenv'][$k]) {
                $leaks[] = 'getenv(\'' . $k . '\') changed';
            }
        }

        // 2. Session save path must still be inside the isolated root.
        $sp = str_replace('\\', '/', (string) ini_get('session.save_path'));
        $root = str_replace('\\', '/', IsolatedEnvironment::runRoot());
        if ($sp !== '' && !str_starts_with($sp, $root)) {
            $leaks[] = 'session.save_path left the isolated root: ' . $sp;
        }

        // 3. No stray top-level entries appeared in the temp root.
        if (is_dir($root)) {
            $stray = array_diff(self::listRoot($root), $this->expectedRootEntries);
            if ($stray !== []) {
                $leaks[] = 'stray entries in temp root: ' . implode(', ', array_map('strval', $stray));
            }
        }

        // 4. No session files survived in the save path (session data must
        //    not persist between cases; lazy_write + strict mode + per-run
        //    name make any file a leak).
        if (is_dir($sp)) {
            $files = array_values(array_diff(scandir($sp) ?: [], ['.', '..']));
            if ($files !== []) {
                $leaks[] = 'session files left in save path: ' . implode(', ', $files);
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
}
