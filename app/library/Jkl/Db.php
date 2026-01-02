<?php

/**
* 
*/
class Jkl_Db extends Jkl_Cache
{
  private $_db;
  private $_queryCount = 0;
  private $_cacheHits = 0;
  
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
    parent::__construct();
  }
  
  /*
  * FechtAll with memcached support
  */
  public function fetchAll($query, $lifeTime = null)
  {
    if (null === $lifeTime) {
      $config = Zend_Registry::get('Config_App');
      $lifeTime = $config['cache']['front']['lifetime'];
    }
    
    $this->_queryCount++;

    $cache = $this->_cache->load(md5($query));
    
    if (false === $cache) {
      $result = $this->_db->fetchAll($query);      
      $test = $this->_cache->save($result, md5($query), array(), $lifeTime);
      // Zend_Registry::get('Logger')->info('DB::read ' . (string)str_replace(array("\r", "\r\n", "\n"), '', $query));
    }
    else {
      $result = $cache;
    }
    return $result;
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
