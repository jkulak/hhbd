<?php
/**
 * Label Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/

class Model_Label_Api extends Jkl_Model_Api
{
  static private $_instance;
  
  /**
   * Singleton instance
   *
   * @return Model_Label_Api
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
  public function getList($query)
  {
    $result = $this->_db->fetchAll($query);
    $list = new Jkl_List(); 
    foreach ($result as $params) {
      $list->add(new Model_Label_Container($params));
    }
    return $list;
  }
  
  public function find($id, $full = false)
  {
    $id = intval($id);
    $query = 'select *, id AS lab_id from labels where id=' . $id;
    $result = $this->_db->fetchAll($query);
    $item = new Model_Label_Container($result[0], $full);
    return $item;
  }
  
  public function getFullList()
  {
    $query = "SELECT t1.id AS lab_id, t1.`name`, t1.`website`, count(t2.id) AS album_count
              FROM labels t1, albums t2
              WHERE (t1.id!=27 AND t2.`labelid`=t1.id)
              GROUP BY t1.`id`
              ORDER BY t1.`name`";
    return $this->getList($query);
  }
  
  public function getWithMostAlbums($limit = 10)
  {
    $limit = intval($limit);
    $query = "SELECT count(t2.id) AS album_count, t1.`id` AS lab_id, t1.`name`
              FROM labels t1, albums t2
              WHERE (t2.`labelid`=t1.`id` AND t1.`id`<>27)
              GROUP BY t1.`id`
              ORDER BY album_count DESC" . 
              (isset($limit)?" LIMIT $limit":'');
              
    return $this->getList($query);
  }
  
  public function getLike($like, $limit = 10, $page = 1)
  {
    $like = Jkl_Db::escape($like);
    $limit = intval($limit);
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    
    $query = "SELECT count(t2.id) AS album_count, t1.`id` AS lab_id, t1.`name`
              FROM labels t1, albums t2
              WHERE (t2.`labelid`=t1.`id` AND t1.`id`<>27 AND t1.`name` LIKE '%$like%')
              GROUP BY t1.`id`
              ORDER BY t1.`viewed` DESC" . 
              (($limit != null)?' LIMIT ' . $limit:'') . 
              ' OFFSET ' . ($page*$limit);
              
    return $this->getList($query);
  }
  
  public function getLikeCount($like = '')
  {
    $like = Jkl_Db::escape($like);
    $query = "SELECT count(*) as count
              FROM labels AS t1
              WHERE t1.name LIKE '%$like%'"; 
    $result = $this->_db->fetchAll($query);
    return intval($result[0]['count']);
  }
  
  public function redirectFromOld($urlName)
  {
    $urlName = strtolower(strval($urlName));
    if (empty($urlName)) {
      return false;
      // throw exception
    }
    $query = "SELECT t1.id AS lab_id, t1.name AS lab_name
              FROM labels t1
              WHERE (t1.`urlname` = '" . $urlName . "');";
    $result = $this->_db->fetchAll($query);
    if (!empty($result[0])) {
      return $result[0];
    }
    return false;
  }
  
  public function redirectById($id)
  {
    $id = intval($id);
    $query = "SELECT t1.id AS lab_id, t1.name AS lab_name
              FROM labels t1
              WHERE (t1.id = '" . $id . "');";
    $result = $this->_db->fetchAll($query);
    if (!empty($result[0])) {
      return $result[0];
    }
    return false;
  }
  
  public function updateView($id)
  {
    $id = intval($id);
    $query = 'UPDATE labels SET viewed=viewed+1 WHERE id=' . $id;
    $this->_db->query($query);
  }
  
  public function getRecent($limit = 20)
  {
    $limit = intval($limit);
    $query = "SELECT *, t1.id AS lab_id, t1.name AS lab_name
              FROM labels t1
              ORDER BY t1.added DESC" .
              (($limit != null)?' LIMIT ' . $limit:'');
    return $this->getList($query);
  }   
}