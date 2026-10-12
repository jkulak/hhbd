<?php

/**
 * Copies a MariaDB database into an empty PostgreSQL one made by its baseline migration, and
 * proves the copy (#233), for the move to PostgreSQL (#229).
 *
 *   php app/tools/mariadb-to-postgres.php copy      copy every table, verify, then commit
 *   php app/tools/mariadb-to-postgres.php verify    compare the two as they are, write nothing
 *
 * Every table but schema_migrations goes, in one transaction on PostgreSQL: the parents of the
 * two foreign keys first, each table's rows in batches, then every identity's next value past
 * the highest id. Before the commit each table is read back from both sides and compared: the
 * row count, and a checksum of the rows that does not depend on their order, as the engines
 * sort text differently. A difference rolls everything back.
 *
 * It refuses a target table that has rows, and a source table or column the target lacks: the
 * schema is the baseline's, not made here. Both databases take DB_NAME, DB_USER and
 * DB_PASSWORD; MariaDB is at MARIADB_HOST (db), PostgreSQL at POSTGRES_HOST (postgres). It
 * reports a JSON line per table on stderr, in the shared host's format (Jkl_Log), and exits 0
 * when the copy is proved, 1 when it is not, 2 when it could not start.
 */

$mode = isset($argv[1]) ? $argv[1] : '';
if (!in_array($mode, array('copy', 'verify'), true)) {
    fwrite(STDERR, "usage: php mariadb-to-postgres.php copy|verify\n");
    exit(2);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../library/Jkl/Log.php';
require_once __DIR__ . '/../library/Jkl/Copy.php';
Jkl_Log::installHandlers('tool');

$log = function ($level, $msg, array $fields = array()) {
    Jkl_Log::write($level, $msg, array('logger' => 'mariadb-to-postgres') + $fields);
};

try {
    $env = function ($name, $default = null) {
        $value = getenv($name);
        if (false === $value || '' === $value) {
            if (null === $default) {
                throw new RuntimeException("$name is not set");
            }
            return $default;
        }
        return $value;
    };
    $options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true);
    $source = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env('MARIADB_HOST', 'db'), $env('MARIADB_PORT', '3306'), $env('DB_NAME')),
        $env('DB_USER'),
        $env('DB_PASSWORD'),
        $options
    );
    $target = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $env('POSTGRES_HOST', 'postgres'), $env('POSTGRES_PORT', '5432'), $env('DB_NAME')),
        $env('DB_USER'),
        $env('DB_PASSWORD'),
        $options
    );
    $copy = new Jkl_Copy($source, $target);
    $tables = $copy->plan();
    if ('copy' === $mode) {
        $copy->assertEmpty($tables);
    }
} catch (Exception $e) {
    $log('error', 'The copy could not start: ' . $e->getMessage(), Jkl_Log::describe($e));
    exit(2);
}

$started = microtime(true);
$target->beginTransaction();
try {
    if ('copy' === $mode) {
        foreach ($tables as $table) {
            $n = $copy->copyTable($table);
            $log('info', sprintf('copied %s: %d rows', $table, $n), array('table' => $table, 'rows' => $n));
        }
        $copy->resetIdentities();
    }
    $differ = array();
    foreach ($tables as $table) {
        list($ok, $sourceRows, $targetRows) = $copy->verifyTable($table);
        $log($ok ? 'info' : 'error', sprintf('%s %s: %d rows in MariaDB, %d in PostgreSQL', $ok ? 'same' : 'DIFFERENT', $table, $sourceRows, $targetRows), array(
            'table' => $table, 'source_rows' => $sourceRows, 'target_rows' => $targetRows, 'same' => $ok,
        ));
        if (!$ok) {
            $differ[] = $table;
        }
    }
    if (!empty($differ)) {
        throw new RuntimeException('the copy differs from the source in ' . implode(', ', $differ));
    }
    'copy' === $mode ? $target->commit() : $target->rollBack();
} catch (Exception $e) {
    $target->rollBack();
    $log('error', ('copy' === $mode ? 'Nothing was copied: ' : 'Not the same: ') . $e->getMessage(), Jkl_Log::describe($e));
    exit(1);
}
$log('info', sprintf('%s %d tables in %.1fs: every one the same on both sides', 'copy' === $mode ? 'copied and verified' : 'verified', count($tables), microtime(true) - $started), array(
    'tables' => count($tables), 'duration_ms' => (int) round((microtime(true) - $started) * 1000),
));
exit(0);
