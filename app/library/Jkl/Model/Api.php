<?php
/**
* 
*/
abstract class Jkl_Model_Api
{
  protected $_db;
  
  function __construct()
  {
    $dbRes = Zend_Registry::get('Config_Resources');
    
    $pdoParams = array( 'MYSQL_ATTR_INIT_COMMAND' => 'SET NAMES utf8' );
    $params = array(
      'host'      => $dbRes['db']['params']['host'],
      'dbname'    => $dbRes['db']['params']['dbname'],
      'username'  => $dbRes['db']['params']['username'],
      'password'  => $dbRes['db']['params']['password'],
      'port'      => (isset($dbRes['db']['params']['port'])?$dbRes['db']['params']['port']:''),
      'charset'   => 'utf8',
      'driver_options' => $pdoParams);
    try
    {
      //Jkl_Db::factory zwraca inny obiekt, dlatego nie diala przeciazenie
      $this->_db = Jkl_Db::getInstance($dbRes['db']['adapter'], $params);
    }
    catch( Zend_Db_Adapter_Exception $e )
    {
      // i tak tutaj nie dochodzi bo wylapuje blad wczesniej zdaje sie
      throw new Jkl_Model_Exception('oh no!', Jkl_Model_Exception::EXCEPTION_DB_CONNECTION_FAILED);
    }
  }
}
