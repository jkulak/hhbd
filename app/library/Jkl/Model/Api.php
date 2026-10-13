<?php

/**
*
*/
abstract class Jkl_Model_Api
{
    protected $_db;

    /** Rows remember() keeps, by id, and the Jkl_Db::writes() they were read at */
    private $_remembered = array();
    private $_rememberedAt;

    public function __construct()
    {
        $dbRes = Zend_Registry::get('Config_Resources');

        // UTF8, values as strings, and statements in one round trip: Jkl_Db::connectionOptions().
        $params = array(
          'host'      => $dbRes['db']['params']['host'],
          'dbname'    => $dbRes['db']['params']['dbname'],
          'username'  => $dbRes['db']['params']['username'],
          'password'  => $dbRes['db']['params']['password'],
          'port'      => (isset($dbRes['db']['params']['port']) ? $dbRes['db']['params']['port'] : ''),
        ) + Jkl_Db::connectionOptions();
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
        return !empty($this->_db->fetchAll("SELECT 1 FROM $table WHERE id = ?", array((int) $id)));
    }

    /**
     * A row this model read by its id earlier in the request, or null (#255). One page names
     * the same artist or label many times, on every album and song that credits it, and used
     * to read it each time. PHP starts every request with nothing kept from the one before, so
     * the rows last no longer than the page; a tool that runs for long forgets them all at its
     * next write.
     */
    protected function remembered($id)
    {
        if ($this->_rememberedAt !== $this->_db->writes()) {
            $this->_remembered = array();
            $this->_rememberedAt = $this->_db->writes();
        }
        return isset($this->_remembered[$id]) ? $this->_remembered[$id] : null;
    }

    /** Keeps a row read by its id for remembered() */
    protected function remember($id, array $row)
    {
        $this->remembered($id);
        $this->_remembered[$id] = $row;
    }
}
