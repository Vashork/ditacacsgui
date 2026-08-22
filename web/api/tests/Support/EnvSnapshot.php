<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use RuntimeException;

/**
 * Minimal env-state snapshot: captures, applies, and restores a fixed set of
 * environment keys across $_ENV, $_SERVER, and putenv().
 *
 * This is the anti-leak primitive for Tasks 4-8: each test case snapshots the
 * harness defaults, applies case-specific overrides, and restores on exit.
 */
final class EnvSnapshot
{
    /** @var list<string> */
    private array $keys;

    /** @var array<string, array{env:?string, server:?string, putenv:?string}> */
    private array $before = [];

    /** @param list<string> $keys environment keys under management */
    public function __construct(array $keys)
    {
        $this->keys = array_values(array_unique($keys));
        $this->capture();
    }

    private function capture(): void
    {
        foreach ($this->keys as $k) {
            $this->before[$k] = [
                'env' => $_ENV[$k] ?? null,
                'server' => $_SERVER[$k] ?? null,
                'putenv' => getenv($k) ?: null,
            ];
        }
    }

    /** Apply a map of key => value to all three env layers. */
    public function apply(array $values): void
    {
        foreach ($values as $k => $v) {
            if (!in_array($k, $this->keys, true)) {
                throw new RuntimeException('Key not under snapshot management: ' . $k);
            }
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
            putenv($k . '=' . $v);
        }
    }

    /** Restore the captured state for every managed key. */
    public function restore(): void
    {
        foreach ($this->keys as $k) {
            $b = $this->before[$k] ?? ['env' => null, 'server' => null, 'putenv' => null];
            if ($b['env'] === null) {
                unset($_ENV[$k]);
            } else {
                $_ENV[$k] = $b['env'];
            }
            if ($b['server'] === null) {
                unset($_SERVER[$k]);
            } else {
                $_SERVER[$k] = $b['server'];
            }
            if ($b['putenv'] === null) {
                putenv($k);
            } else {
                putenv($k . '=' . $b['putenv']);
            }
        }
    }

    /** @return list<string> */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * The captured (current) env state for the managed keys, in the shape
     * StateGuard expects for baseline comparison.
     *
     * @return array{env:array<string,string>,putenv:array<string,string>}
     */
    public function state(): array
    {
        $env = [];
        $putenv = [];
        foreach ($this->keys as $k) {
            if (($_ENV[$k] ?? null) !== null) {
                $env[$k] = (string) $_ENV[$k];
            }
            $v = getenv($k);
            if ($v !== false && $v !== '') {
                $putenv[$k] = (string) $v;
            }
        }
        return ['env' => $env, 'putenv' => $putenv];
    }
}
