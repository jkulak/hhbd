<?php

/**
 * A MariaDB database copied into PostgreSQL, table by table, and the proof that the copy is the
 * same (#233). app/tools/mariadb-to-postgres.php runs it; both connections fetch every value as
 * a string, as PDO MySQL hands them.
 */
class Jkl_Copy
{
    /** The parents of the two foreign keys go before their children; the rest by name */
    public const FIRST = array('import_runs', 'edit_operations');

    /** Not copied: each database records its own migrations */
    public const SKIP = array('schema_migrations');

    /** Bound values per INSERT, under PostgreSQL's 65535 */
    public const VALUES_PER_INSERT = 30000;

    private $_source;
    private $_target;
    private $_columns = array();

    public function __construct(PDO $source, PDO $target)
    {
        $this->_source = $source;
        $this->_target = $target;
    }

    /**
     * The tables to copy, in order, after checking that the target has each of them with the
     * same columns, and none of them holds a row
     *
     * @return string[]
     */
    public function plan()
    {
        $source = $this->sourceColumns();
        $target = $this->targetColumns();
        $problems = array();
        foreach ($source as $table => $columns) {
            if (!isset($target[$table])) {
                $problems[] = "PostgreSQL has no table $table";
                continue;
            }
            $missing = array_diff($columns, $target[$table]);
            $extra = array_diff($target[$table], $columns);
            if (!empty($missing) || !empty($extra)) {
                $problems[] = sprintf('%s differs: %s', $table, implode(', ', array_merge(
                    array_map(function ($c) { return "no $c in PostgreSQL"; }, $missing),
                    array_map(function ($c) { return "no $c in MariaDB"; }, $extra)
                )));
            }
        }
        foreach (array_diff_key($target, $source) as $table => $columns) {
            $problems[] = "MariaDB has no table $table";
        }
        if (!empty($problems)) {
            throw new RuntimeException('the schemas differ: ' . implode('; ', $problems));
        }
        $this->_columns = $source;
        $tables = array_keys($source);
        sort($tables);
        $tables = array_values(array_unique(array_merge(array_intersect(self::FIRST, $tables), $tables)));
        return $tables;
    }

    /** Refuses a target table with rows: a copy goes into an empty database only */
    public function assertEmpty(array $tables)
    {
        $full = array();
        foreach ($tables as $table) {
            if ($this->_target->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                $full[] = $table;
            }
        }
        if (!empty($full)) {
            throw new RuntimeException('PostgreSQL already has rows in ' . implode(', ', $full) . '; the copy goes into an empty database');
        }
    }

    /** Copies one table's rows; @return int how many */
    public function copyTable($table)
    {
        $columns = $this->_columns[$table];
        $perInsert = max(1, (int) floor(self::VALUES_PER_INSERT / count($columns)));
        $rows = $this->_source->query('SELECT ' . implode(', ', $columns) . ' FROM ' . $table);
        $batch = array();
        $n = 0;
        while (false !== ($row = $rows->fetch(PDO::FETCH_NUM))) {
            $batch[] = $row;
            if (count($batch) === $perInsert) {
                $n += $this->insert($table, $columns, $batch);
                $batch = array();
            }
        }
        if (!empty($batch)) {
            $n += $this->insert($table, $columns, $batch);
        }
        return $n;
    }

    /** Sets every identity's next value past the highest id the copy brought */
    public function resetIdentities()
    {
        $identities = $this->_target->query(
            "SELECT table_name, column_name FROM information_schema.columns
              WHERE table_schema = current_schema() AND is_identity = 'YES'"
        )->fetchAll(PDO::FETCH_NUM);
        foreach ($identities as list($table, $column)) {
            $this->_target->query(sprintf(
                "SELECT setval(pg_get_serial_sequence('%s', '%s'), COALESCE(MAX(%s), 1), MAX(%s) IS NOT NULL) FROM %s",
                $table,
                $column,
                $column,
                $column,
                $table
            ));
        }
    }

    /**
     * Compares one table on both sides: the row count, and the checksum of its rows
     *
     * @return array(bool same, int source rows, int target rows)
     */
    public function verifyTable($table)
    {
        $columns = $this->_columns[$table];
        $select = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $table;
        list($sourceRows, $sourceSum) = self::checksum($this->_source->query($select, PDO::FETCH_NUM));
        list($targetRows, $targetSum) = self::checksum($this->_target->query($select, PDO::FETCH_NUM));
        return array($sourceRows === $targetRows && $sourceSum === $targetSum, $sourceRows, $targetRows);
    }

    /**
     * The rows' count and a checksum that does not depend on their order: each row's values,
     * NULL told apart from '', hashed, the hashes sorted, and hashed again
     *
     * @param Traversable|array $rows rows of values as strings or NULL
     * @return array(int, string)
     */
    public static function checksum($rows)
    {
        $hashes = array();
        foreach ($rows as $row) {
            $hashes[] = md5(json_encode(array_map(function ($value) {
                return null === $value ? null : (string) $value;
            }, array_values($row)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        sort($hashes, SORT_STRING);
        return array(count($hashes), sha1(implode('', $hashes)));
    }

    private function insert($table, array $columns, array $rows)
    {
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $statement = $this->_target->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', array_fill(0, count($rows), $placeholders))
        );
        $statement->execute(array_merge(...$rows));
        return count($rows);
    }

    /** @return array table => its columns in order, lowercase */
    private function sourceColumns()
    {
        return $this->columnsOf($this->_source, 'DATABASE()');
    }

    private function targetColumns()
    {
        return $this->columnsOf($this->_target, 'current_schema()');
    }

    private function columnsOf(PDO $db, $schema)
    {
        $rows = $db->query(
            "SELECT c.table_name, c.column_name FROM information_schema.columns c
               JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name
              WHERE c.table_schema = $schema AND t.table_type = 'BASE TABLE'
              ORDER BY c.table_name, c.ordinal_position"
        )->fetchAll(PDO::FETCH_NUM);
        $columns = array();
        foreach ($rows as list($table, $column)) {
            if (!in_array(strtolower($table), self::SKIP, true)) {
                $columns[strtolower($table)][] = strtolower($column);
            }
        }
        return $columns;
    }
}
