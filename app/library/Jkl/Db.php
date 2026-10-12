<?php

/**
*
*/
class Jkl_Db
{
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
