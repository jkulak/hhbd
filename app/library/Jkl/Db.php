<?php

/**
 * The application's database connection, and the few things its SQL cannot say the same way on
 * MariaDB and PostgreSQL (#231): an upsert, the search's case- and accent-insensitive LIKE, one
 * row of several alike, a table's unique keys, a duplicate key's error. Everything else is SQL
 * both engines run, which MariaDB's ANSI_QUOTES and ONLY_FULL_GROUP_BY hold the code to.
 */
class Jkl_Db
{
    /**
     * How a search compares on MariaDB (#151): case and Polish letters do not count, so
     * "wzgorze" finds "Wzgórze" and "lona" finds "Łona", as people type on a phone. The columns'
     * own utf8mb4_polish_ci, right for sorting, tells ó from o and ł from l.
     */
    public const SEARCH_COLLATION = 'utf8mb4_uca1400_ai_ci';

    /**
     * What each of the application's MariaDB sessions starts with (#231): double quotes name
     * columns and grouping names every column it shows, as in PostgreSQL, so SQL that
     * PostgreSQL would refuse fails on MariaDB too. 1002 is PDO::MYSQL_ATTR_INIT_COMMAND, by
     * number as Zend writes it, which needs no constant PHP 8.5 renames.
     */
    public const MARIADB_SESSION = array(1002 => "SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',ANSI_QUOTES,ONLY_FULL_GROUP_BY')");

    private $_db;
    private $_queryCount = 0;

    private static $_instance;

    /**
     * Singleton instance
     *
     * @return Jkl_Db
     */
    public static function getInstance($adapter = null, $params = null)
    {
        if (null === self::$_instance) {
            self::$_instance = new self($adapter, $params);
        }

        return self::$_instance;
    }

    public function __construct($adapter, $params)
    {
        $this->_db = Zend_Db::factory($adapter, $params);
    }

    /**
     * What a connection through $adapter needs beyond the host and the credentials (#232):
     * MariaDB's utf8mb4 and its portable SQL modes; PostgreSQL's UTF8, values fetched as
     * strings, as PDO MySQL hands them and the code has always read them, and each statement
     * sent with its values in one round trip rather than prepared, bound and run in three:
     * a page runs hundreds of small queries, and the rehearsal on production's data (#235)
     * measured the prepared ones adding a tenth to PostgreSQL's pages. The values stay bound,
     * never put into the SQL. Connections are not persistent, though they would save some 5 ms
     * a page: a PHP-FPM worker would keep a connection PostgreSQL has dropped, after a restart
     * or a reset, and its next request would fail.
     *
     * @return array charset, driver_options
     */
    public static function connectionOptions($adapter)
    {
        if (self::isPostgresAdapter($adapter)) {
            return array('charset' => 'UTF8', 'driver_options' => array(
                PDO::ATTR_STRINGIFY_FETCHES => true,
                Pdo\Pgsql::ATTR_DISABLE_PREPARES => true,
            ));
        }
        return array('charset' => 'utf8mb4', 'driver_options' => self::MARIADB_SESSION);
    }

    /** Whether an adapter name, as application.ini and DB_ADAPTER give it, is PostgreSQL's */
    public static function isPostgresAdapter($adapter)
    {
        return 'pdo_pgsql' === strtolower((string) $adapter);
    }

    /** Whether this connection is to PostgreSQL */
    public function isPostgres()
    {
        return $this->_db instanceof Zend_Db_Adapter_Pdo_Pgsql;
    }

    /*
    * FechtAll
    *
    * $bind holds values for the query's ? or :name placeholders. New code passes values that way
    * rather than escaping them into the query string, since they may come from outside sources.
    */
    public function fetchAll($query, array $bind = array())
    {
        $this->_queryCount++;
        return $this->_db->fetchAll($query, $bind);
    }

    /*
    * Used for non cached queries (like UPDATE); $bind as in fetchAll()
    */
    public function query($query, array $bind = array())
    {
        $this->_queryCount++;
        return $this->_db->query($query, $bind);
    }

    /** A table's or a column's name quoted as the engine quotes them, for names a query is built with */
    public function quoteIdentifier($name)
    {
        return $this->_db->quoteIdentifier($name);
    }

    /**
     * "$column LIKE ?" matching regardless of case and of Polish letters, as the search does
     * (#151): "lona" finds "Łona"; bind a pattern from contains() or startsWith()
     */
    public function searchLike($column)
    {
        if ($this->isPostgres()) {
            // unaccent turns ó into o and ł into l, lower() the case; both sides alike (#232)
            return 'unaccent(lower(' . $column . ')) LIKE unaccent(lower(?))';
        }
        return $column . ' LIKE ? COLLATE ' . self::SEARCH_COLLATION;
    }

    /**
     * Inserts a row, or when a unique key already holds one like it, changes that one: $update
     * maps a column to true for the value this insert brought, or to an expression on the row
     * there, its columns named with the table's name, such as "page_views.views + 1". With no
     * $update the row there is left as it is; with one, $conflict names the unique key's columns.
     *
     * @return bool whether a row was inserted or changed (false: one was there, and stayed)
     */
    public function upsert($table, array $row, array $update = array(), array $conflict = array())
    {
        $columns = array_keys($row);
        $insert = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        if ($this->isPostgres()) {
            $set = array();
            foreach ($update as $column => $expression) {
                $set[] = $column . ' = ' . (true === $expression ? 'EXCLUDED.' . $column : $expression);
            }
            $statement = $this->query(
                $insert . (empty($set)
                    ? ' ON CONFLICT DO NOTHING'
                    : ' ON CONFLICT (' . implode(', ', $conflict) . ') DO UPDATE SET ' . implode(', ', $set)),
                array_values($row)
            );
            return $statement->rowCount() > 0;
        }
        $set = array();
        foreach ($update as $column => $expression) {
            $set[] = $column . ' = ' . (true === $expression ? 'VALUES(' . $column . ')' : $expression);
        }
        if (empty($set)) {
            // MariaDB has no DO NOTHING; setting a column to itself changes no row and counts
            // none, as INSERT IGNORE would, without ignoring every other error with it.
            $set[] = $columns[0] . ' = ' . $columns[0];
        }
        $statement = $this->query($insert . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $set), array_values($row));
        return $statement->rowCount() > 0;
    }

    /**
     * UPDATE of one row among those $where matches, which a table without a key may hold
     * twice; $bind holds $set's values, then $where's
     */
    public function updateOne($table, $set, $where, array $bind = array())
    {
        if ($this->isPostgres()) {
            // No LIMIT on an UPDATE: the row is picked by its physical address, ctid
            return $this->query('UPDATE ' . $table . ' SET ' . $set . ' WHERE ctid = (SELECT ctid FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1)', $bind);
        }
        return $this->query('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where . ' LIMIT 1', $bind);
    }

    /** DELETE of one row among those $where matches, as updateOne() has it */
    public function deleteOne($table, $where, array $bind = array())
    {
        if ($this->isPostgres()) {
            return $this->query('DELETE FROM ' . $table . ' WHERE ctid = (SELECT ctid FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1)', $bind);
        }
        return $this->query('DELETE FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1', $bind);
    }

    /**
     * A table's unique keys, the primary one first
     *
     * @return array index name => its columns, in order
     */
    public function uniqueKeys($table)
    {
        if ($this->isPostgres()) {
            // An index on an expression, lower(name), names no column there and is left out
            $rows = $this->fetchAll(
                "SELECT i.relname AS index_name, a.attname AS column_name
                   FROM pg_index x
                   JOIN pg_class t ON t.oid = x.indrelid
                   JOIN pg_class i ON i.oid = x.indexrelid
                   JOIN LATERAL unnest(x.indkey) WITH ORDINALITY AS k(attnum, n) ON true
                   JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = k.attnum
                  WHERE t.relname = ? AND t.relnamespace = current_schema()::regnamespace AND x.indisunique
                    AND x.indexprs IS NULL
                  ORDER BY x.indisprimary DESC, i.relname, k.n",
                array($table)
            );
        } else {
            $rows = $this->fetchAll(
                "SELECT index_name, column_name FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 0
              ORDER BY index_name = 'PRIMARY' DESC, index_name, seq_in_index",
                array($table)
            );
        }
        $keys = array();
        foreach ($rows as $row) {
            $keys[$row['index_name']][] = $row['column_name'];
        }
        return $keys;
    }

    /**
     * ORDER BY that keeps rows in the order of $ids, MariaDB's FIELD() in standard SQL: a CASE
     * that gives each id its place
     */
    public static function inOrder($column, array $ids)
    {
        $when = array();
        foreach (array_values($ids) as $place => $id) {
            $when[] = 'WHEN ' . (int) $id . ' THEN ' . $place;
        }
        return empty($when) ? $column : 'CASE ' . $column . ' ' . implode(' ', $when) . ' END';
    }

    /** Whether a failed statement broke a unique key: SQLSTATE 23505, or MariaDB's 23000 with 1062 */
    public static function isDuplicateKey(Exception $e)
    {
        $code = (string) $e->getCode();
        return '23505' === $code || ('23000' === $code && false !== strpos($e->getMessage(), '1062'));
    }

    /*
    * The id the last INSERT gave its row, on this connection
    */
    public function lastInsertId()
    {
        return $this->_db->lastInsertId();
    }

    /*
    * Transactions, for writes that belong together (the importer writes a release with its
    * tracks and credits as one): all of them or none.
    */
    public function beginTransaction()
    {
        $this->_db->beginTransaction();
    }

    public function commit()
    {
        $this->_db->commit();
    }

    public function rollBack()
    {
        $this->_db->rollBack();
    }

    public function getQueryCount()
    {
        return $this->_queryCount;
    }

    /**
     * A LIKE pattern that finds $text anywhere in a value, bound as a parameter, never written
     * into the query (#230). Its own % and _ are matched as themselves, not as wildcards; the
     * backslash before them is LIKE's escape on MariaDB and PostgreSQL alike.
     */
    public static function contains($text)
    {
        return '%' . self::likeLiteral($text) . '%';
    }

    /** A LIKE pattern that finds values starting with $text, as contains() has it */
    public static function startsWith($text)
    {
        return self::likeLiteral($text) . '%';
    }

    private static function likeLiteral($text)
    {
        return addcslashes((string) $text, '\\%_');
    }
}
