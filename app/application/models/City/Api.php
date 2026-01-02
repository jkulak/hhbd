<?php
/**
 * City Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/

class Model_City_Api extends Jkl_Model_Api
{  
  static private $_instance;
  
  /**
   * Singleton instance
   *
   * @return Model_City_Api
   */
  public static function getInstance()
  {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }

      return self::$_instance;
  }
  
  public function getArtistCities($id)
  {
    $query = 'SELECT t1.name AS city FROM cities AS t1, artists AS t2, artist_city_lookup AS t3 ' .
    	   'WHERE (t3.cityid=t1.id AND t3.artistid=t2.id AND t2.id=' . $id . ')';
    $result = $this->_db->fetchAll($query);
    $list = new Jkl_List('Also known as list');
    foreach ($result as $key => $value) {
      $list->add($value);
    }
    return $list;
  }
}