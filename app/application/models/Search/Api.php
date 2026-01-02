<?php
/**
 * Search Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package hhbd
 **/

class Model_Search_Api extends Jkl_Model_Api
{

  static private $_instance;

  /**
   * Singleton instance
   *
   * @return Model_Search_Api
   */
  public static function getInstance()
  {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }

      return self::$_instance;
  }

  public function getRecent($limit = 15)
  {
    $limit = intval($limit);
    $query = "SELECT DISTINCT(t1.searchstring) AS sea_query, t1.id
              FROM searches t1
              ORDER BY id DESC" .
              (($limit != null)?' LIMIT ' . $limit:'');
    $result = $this->_db->fetchAll($query);
    return $result;
  }

  public function getMostPopular($limit = 15)
  {
    $limit = intval($limit);
    $query = "SELECT DISTINCT(t1.searchstring) AS sea_query, count(t1.searchstring) AS sea_count
              FROM searches t1
              GROUP BY t1.`searchstring`
              ORDER BY sea_count DESC" .
              (($limit != null)?' LIMIT ' . $limit:'');
    $result = $this->_db->fetchAll($query);
    return $result;
  }

  public function saveSearch($query)
  {
    $query = Jkl_Db::escape($query);
    if (!isset($query)) {
      throw Jkl_Exception("Empty search query can't be saved to database!");
    }
    $query = "INSERT INTO searches SET searchstring='$query';";
    $result = $this->_db->query($query);

    // how to check query result ?
    return true;
  }
}
