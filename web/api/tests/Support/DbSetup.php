<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use RuntimeException;

/**
 * Fail-closed SQLite setup seam for the isolated test harness (Task 2).
 *
 * This is the ONLY code path that creates/initializes the test SQLite
 * databases. tests/bootstrap.php calls prepareSqliteFile() for the two
 * per-run files; TestAppFactory runs the production SchemaBuilderSqlite
 * against the prepared files. The unwritable-path sentinel test drives
 * prepareSqliteFile() with a bad path and asserts the real exception -
 * no mirrored PDO statements.
 *
 * There is deliberately NO fallback to MySQL or any other driver: every
 * failure throws a controlled RuntimeException naming the offending path.
 */
final class DbSetup
{
    /**
     * Create $path as a REAL, writable, empty SQLite database.
     *
     * Fails closed (RuntimeException, no fallback) when:
     *  - the parent directory cannot be created (e.g. a regular file
     *    occupies the parent path - the portable "unwritable" sentinel),
     *  - the file cannot be created, or is not writable afterwards.
     *
     * A zero-length file is not acceptable: PDO sqlite rejects it with
     * "file is not a database", so an init table is written.
     *
     * @param string $path absolute path of the sqlite file to create
     * @return void
     * @throws RuntimeException controlled failure, message names $path
     */
    public static function prepareSqliteFile(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true)) {
                throw new RuntimeException('DbSetup: cannot create SQLite directory (fail-closed, no MySQL fallback): ' . $dir);
            }
        }
        if (!is_file($path)) {
            if (!@touch($path)) {
                throw new RuntimeException('DbSetup: cannot create SQLite file (fail-closed, no MySQL fallback): ' . $path);
            }
        }
        if (!is_writable($path)) {
            throw new RuntimeException('DbSetup: SQLite file is not writable (fail-closed, no MySQL fallback): ' . $path);
        }
        $pdo = new \PDO('sqlite:' . $path); // throws PDOException if $path is unusable
        $pdo->exec('CREATE TABLE IF NOT EXISTS _harness_init (id INTEGER PRIMARY KEY);');
        $pdo = null;
    }
}
