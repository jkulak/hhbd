<?php
/**
 * News Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package hhbd
 **/

class Model_News_Api extends Jkl_Model_Api
{
  
  static private $_instance;
  
  /**
   * Singleton instance
   *
   * @return Model_News_Api
   */
  public static function getInstance()
  {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }

      return self::$_instance;
  }

  /**
   * Creates object and fetches the list from database result
   */
  private function _getList($query)
  {
    $result = $this->_db->fetchAll($query);
    $list = new Jkl_List(); 
    foreach ($result as $params) {
      $list->add(new Model_News_Container($params));
    }
    return $list;
  }
  
  /*
  * Get list of most recent news
  */
  public function getRecent($limit = 10, $full = true)
  {
    $limit = intval($limit);
    $query = "SELECT t1.id AS nws_id, t1.title AS nws_title, t1.news AS nws_content, t1.graph AS nws_attachment_url,
              t1.added AS nws_added, t1.addedby AS new_added_by
              FROM news t1
              ORDER BY t1.added DESC
              " . (($limit)?'LIMIT ' . $limit:'');
    return $this->_getList($query);
  }
  
  /*
  * Get news detail
  */
  public function find($id, $full = false)
  {
    $id = intval($id);
    
    $query = "SELECT t1.id AS nws_id, t1.title AS nws_title, t1.news AS nws_content, t1.graph AS nws_attachment_url,
              t1.added AS nws_added, t1.addedby AS nws_added_by
              FROM news t1
              WHERE (t1.id=$id)";
    $result = $this->_db->fetchAll($query);
    return new Model_News_Container($result[0], $full);
  }
  
  public function redirectById($id)
  {
    $id = intval($id);
    $query = "SELECT t1.id AS nws_id, t1.title AS nws_title
              FROM news t1
              WHERE (t1.id=$id)";
    $result = $this->_db->fetchAll($query);
    return $result[0];
  }
  
  public function updateView($id)
  {
    $id = intval($id);
    $query = 'UPDATE news SET viewed=viewed+1 WHERE id=' . $id;
    $this->_db->query($query);
  }
}