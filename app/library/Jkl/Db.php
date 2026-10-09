<?php

/**
* 
*/
class Jkl_Db
{
  private $_db;
  private $_queryCount = 0;

  static private $_instance;

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

  function __construct($adapter, $params) {
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
  
  static public function escape($value)
  {
    $search = array("\\", "\0", "\n", "\r", "\x1a", "'", '"', '%');
    $replace = array("\\\\", "\\0", "\\n", "\\r", "\Z", "\'", '\"', '\%');
    return str_replace($search, $replace, $value);
  }
  
}
