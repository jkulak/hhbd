<?php
/**
 * Album Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/
 
// extends Api
class Model_Rating_Api extends Jkl_Model_Api
{
  
  static private $_instance;
  
  /**
   * Singleton instance
   *
   * @return Model_Rating_Api
   */
  public static function getInstance()
  {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }

      return self::$_instance;
  }
  
  public function getAlbumRating($id)
  {
    $query = 'SELECT rating FROM ratings_avg WHERE albumid=' . $id;
    $result = $this->_db->fetchAll($query);
    if (isset($result[0])) {
      $rating = $result[0]['rating'];
    }
    else {
      $rating = '';
    }
    return $rating;
  }

  public function getAlbumVoteCount($id)
  {
     $query = 'SELECT COUNT(id) as votecount FROM ratings WHERE albumid="' . $id . '"';
     $result = $this->_db->fetchAll($query);
     return $result[0]['votecount'];
  }

}