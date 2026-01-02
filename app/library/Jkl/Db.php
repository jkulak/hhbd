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
  */
  public function fetchAll($query)
  {
    $this->_queryCount++;
    return $this->_db->fetchAll($query);
  }
  
  /*
  * Used for non cached queries (like UPDATE)
  */
  public function query($query)
  {
    $this->_queryCount++;
    return $this->_db->query($query);
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
