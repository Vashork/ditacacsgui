<?php

declare(strict_types=1);

namespace tgui\Tests;

use PHPUnit\Framework\TestCase;
use tgui\Tests\Support\IsolatedEnvironment;
use tgui\Tests\Support\TestAppFactory;

/**
 * Regression guard: the harness must never create, rename, write, delete,
 * or restore the repository storage/sqlite directory (the rejected
 * harness stashed it, let the production bootstrap recreate it, and
 * restored it - every run rewrote the repo dev DB; proven by the
 * failing-first hash evidence in .omo/evidence/task-2-correction/).
 *
 * The test snapshots the directory's state (absent / present+hashes)
 * BEFORE building the app and live-PDO-connecting, then asserts the
 * state is byte-identical afterwards.
 */
final class RepoStorageGuardTest extends TestCase
{
    private static function repoDir(): string
    {
        return dirname(__DIR__) . '/storage/sqlite';
    }

    /** @return array{0:bool,1:array<string,string>} [exists, path=>sha256] */
    private static function snapshot(): array
    {
        $repo = self::repoDir();
        if (!is_dir($repo)) {
            return [false, []];
        }
        $hashes = [];
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($repo, \FilesystemIterator::SKIP_DOTS)
        ) as $it) {
            if (!$it->isDir()) {
                $hashes[$it->getPathname()] = (string) hash_file('sha256', $it->getPathname());
            }
        }
        return [true, $hashes];
    }

    public function testRepoStorageIsNeverTouchedByTheHarness(): void
    {
        $repo = self::repoDir();
        $apiRoot = dirname(__DIR__);

        // 1. No stash/tombstone residue anywhere in the repo (the rejected
        //    harness left .t2stash-* dirs and .t2-residue-sqlite markers).
        $stashes = glob($repo . '.t2stash-*') ?: [];
        $this->assertSame([], $stashes, 'repo storage/sqlite was renamed into a stash by the harness: ' . implode(', ', $stashes));
        $tombstones = glob($apiRoot . '/storage/.t2-residue-sqlite') ?: [];
        $this->assertSame([], $tombstones, 'repo storage tombstone present: ' . implode(', ', $tombstones));

        // 2. Snapshot the repo storage state before any app activity.
        [$preExists, $preHashes] = self::snapshot();

        // 3. Build the app (the REAL production bootstrap runs here) and
        //    force both live connections open. The rejected harness
        //    recreated the repo dir exactly at this step (its own bootstrap
        //    ran the hardcoded sqlite branch of bootstrap/app.php).
        $app = TestAppFactory::app();
        $this->assertInstanceOf(\Slim\App::class, $app);
        $capsule = $app->getContainer()->get('db');
        $this->assertInstanceOf(\PDO::class, $capsule->connection('default')->getPdo());
        $this->assertInstanceOf(\PDO::class, $capsule->connection('logging')->getPdo());

        // 4. Postcondition: byte-identical state (absent stays absent;
        //    present keeps every file hash; no new files).
        [$postExists, $postHashes] = self::snapshot();
        $this->assertSame($preExists, $postExists, 'repo storage/sqlite existence changed by the harness: pre=' . var_export($preExists, true) . ' post=' . var_export($postExists, true));
        $this->assertSame($preHashes, $postHashes, 'repo storage/sqlite contents changed by the harness: pre=' . json_encode($preHashes) . ' post=' . json_encode($postHashes));

        // 5. No new stashes/tombstones appeared during the run.
        $this->assertSame([], glob($repo . '.t2stash-*') ?: [], 'repo storage/sqlite stashed after app build');
        $this->assertSame([], glob($apiRoot . '/storage/.t2-residue-sqlite') ?: [], 'repo storage tombstone left behind by the run');
    }
}
