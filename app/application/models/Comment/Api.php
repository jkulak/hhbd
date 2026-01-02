<?php
/**
 * Comment Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package hhbd
 **/

class Model_Comment_Api extends Jkl_Model_Api
{
  
  static private $_instance;
  
  /**
   * Singleton instance
   *
   * @return Model_Comment_Api
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
    $result = $this->_db->fetchAll($query, 90);
    $list = new Jkl_List(); 
    foreach ($result as $params) {
      $list->add(new Model_Comment_Container($params));
    }
    return $list;
  }
  
  public function postComment($content, $author, $authorIp, $objectId, $objectType, $authorId = null)
  {
    $content = addslashes($content);
    $author = addslashes($author);
    $authorIp = addslashes($authorIp);
    $objectType = addslashes($objectType);
    $objectId = intval($objectId);
    $authorId = intval($authorId);
  
    $query = "INSERT INTO hhb_comments
              (com_content, com_author, com_author_ip, com_author_id, com_object_type, com_object_id, com_added)
              VALUES ('$content', '$author', '$authorIp', '$authorId', '$objectType', '$objectId', '" . date("Y-m-d H:i:s") . "')";
              
    $result = $this->_db->query($query);
    return true;
  }
  
  
  public function getComments($id, $type, $page = 0, $limit = 250) {
    $id = intval($id);
    $type = strval($type[0]);
    $page = intval($page);
    $limit = intval($limit);
    
    $query = "SELECT *
              FROM hhb_comments
              WHERE (com_object_id='$id' AND com_object_type='$type')
              ORDER BY com_added DESC" .
              (($limit != null)?' LIMIT ' . $limit:'') .
              ' OFFSET ' . ($page*$limit);
    return $this->_getList($query);
  }
}