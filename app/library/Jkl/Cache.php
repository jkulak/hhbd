<?php

/**
*
*/
class Jkl_Cache
{
  public $_cache;
  static private $_instance;

  function __construct()
  {
    $this->_cache = $this->_initMemcached();
  }

  /**
   * Singleton instance
   *
   * @return Jkl_Db
   */
  public static function getInstance($adapter = null, $params = null)
  {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }
      return self::$_instance;
  }

  private function _initMemcached()
  {
    $config = Zend_Registry::get('Config_App');

     // Use BlackHole backend when caching is disabled (no memcached needed)
     if (!$config['cache']['front']['caching']) {
       $oBackend = new Zend_Cache_Backend_BlackHole();
     } else {
       $oBackend = new Zend_Cache_Backend_Libmemcached(array(
             'servers' =>array(
               array(
               'host' => $config['cache']['backend']['host'],
               'port' => $config['cache']['backend']['port']
               )
             ),
             'compression' => $config['cache']['backend']['compression']
           ));
     }

     $oFrontend = new Zend_Cache_Core(
         array(
             'caching' => $config['cache']['front']['caching'],
             'cache_id_prefix' => 'wwwhhbdpl',
             'logging' => false,
             'write_control' => true,
             'automatic_serialization' => $config['cache']['front']['automatic_serialization'],
             'ignore_user_abort' => true,
             'lifetime' => $config['cache']['front']['lifetime']
         ) );

     return Zend_Cache::factory($oFrontend, $oBackend);
   }
}
