<?php

/**
*
*/
abstract class Jkl_Model_Api
{
    /**
     * How a search compares (#151): case and Polish letters do not count, so "wzgorze" finds
     * "Wzgórze" and "lona" finds "Łona", as people type on a phone. The columns' own
     * utf8mb4_polish_ci, right for sorting, tells ó from o and ł from l.
     */
    public const SEARCH_COLLATION = 'utf8mb4_uca1400_ai_ci';

    protected $_db;

    public function __construct()
    {
        $dbRes = Zend_Registry::get('Config_Resources');

        // utf8mb4, so names with characters outside the Basic Multilingual Plane survive the
        // trip both ways (#71); utf8 is MariaDB's three-byte utf8mb3.
        $pdoParams = array( 'MYSQL_ATTR_INIT_COMMAND' => 'SET NAMES utf8mb4' );
        $params = array(
          'host'      => $dbRes['db']['params']['host'],
          'dbname'    => $dbRes['db']['params']['dbname'],
          'username'  => $dbRes['db']['params']['username'],
          'password'  => $dbRes['db']['params']['password'],
          'port'      => (isset($dbRes['db']['params']['port']) ? $dbRes['db']['params']['port'] : ''),
          'charset'   => 'utf8mb4',
          'driver_options' => $pdoParams);
        try {
            //Jkl_Db::factory zwraca inny obiekt, dlatego nie diala przeciazenie
            $this->_db = Jkl_Db::getInstance($dbRes['db']['adapter'], $params);
        } catch (Zend_Db_Adapter_Exception $e) {
            // i tak tutaj nie dochodzi bo wylapuje blad wczesniej zdaje sie
            throw new Jkl_Model_Exception('oh no!', Jkl_Model_Exception::EXCEPTION_DB_CONNECTION_FAILED);
        }
    }

    /**
     * Whether $table has a row with this id. A page asks before it builds the row, so a row that
     * is not there is a 404 at its own address rather than a page made of nothing, which
     * redirected to /a.html and the like (#147).
     */
    protected function has($table, $id)
    {
        return !empty($this->_db->fetchAll("SELECT 1 FROM `$table` WHERE id = ?", array((int) $id)));
    }
}
