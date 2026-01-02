<?php
/**
 * Artist Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/

class Model_Artist_Api extends Jkl_Model_Api
{
  static private $_instance;

  /**
   * Singleton instance
   *
   * @return Model_Artist_Api
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
    $artists = new Jkl_List();

    foreach ($result as $params) {
      $artists->add(new Model_Artist_Container($params));
    }
    return $artists;
  }

  public function getFirstLetters()
  {
    $query = 'SELECT DISTINCT(SUBSTR(name, 1,1)) as name from artists order by name ASC';
    return  $this->_db->fetchAll($query);
  }

  public function getLikeLetter($like = '', $limit = 15, $page = 1)
  {
    $like = Jkl_Db::escape($like);
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    
    $query = "SELECT *, t1.id as art_id
              FROM artists AS t1
              WHERE (t1.name LIKE '$like%' )
              ORDER BY t1.viewed DESC" .
              (($limit != null)?' LIMIT ' . $limit . ' OFFSET ' . ($page*$limit):'');
      
    $result = $this->_db->fetchAll($query);
    $artists = new Jkl_List();

    foreach ($result as $params) {
      $params['aka'] = $this->_getAka($params['art_id']);
      $params['albumCount'] = Model_Album_Api::getInstance()->getArtistsAlbumsCount($params['art_id']);
      $artists->add(new Model_Artist_Container($params));
    }

    return $artists;
  }

  public function getLike($like = '', $limit = 15, $page = 1)
  {
    $like = Jkl_Db::escape($like);
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    
    $query = "SELECT *, t1.id as art_id
              FROM artists AS t1
              WHERE (t1.name LIKE '%$like%' )
              ORDER BY t1.viewed DESC" .
              (($limit != null)?' LIMIT ' . $limit . ' OFFSET ' . ($page*$limit):'');
      
    $result = $this->_db->fetchAll($query);
    $artists = new Jkl_List();

    foreach ($result as $params) {
      $params['aka'] = $this->_getAka($params['art_id']);
      $params['albumCount'] = Model_Album_Api::getInstance()->getArtistsAlbumsCount($params['art_id']);
      $artists->add(new Model_Artist_Container($params));
    }

    return $artists;
  }
  
  public function getNicknamesLike($like = '', $limit = 15, $page = 1)
  {
    $like = Jkl_Db::escape($like);
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    
    $query = "SELECT *, t1.id AS art_id 
              FROM `artists` t1, `altnames_lookup` t2
              WHERE (t1.id=t2.`artistid` AND t2.`altname` LIKE '%$like%')" .
              "ORDER BY t1.viewed DESC" .  
              (($limit != null)?' LIMIT ' . $limit:'') . 
              ' OFFSET ' . ($page*$limit);
      
    $result = $this->_db->fetchAll($query);
    $artists = new Jkl_List();

    foreach ($result as $params) {
      $params['aka'] = $this->_getAka($params['art_id']);
      $params['albumCount'] = Model_Album_Api::getInstance()->getArtistsAlbumsCount($params['art_id']);
      $artists->add(new Model_Artist_Container($params));
    }

    return $artists;
  }

  public function getMostPopular($limit = 40)
  {
    $limit = intval($limit);
    $query = 'SELECT *, t1.id as art_id ' .
      'FROM artists AS t1 ' .
      'ORDER BY t1.viewed DESC ' . 
      (($limit)?'LIMIT ' . $limit:'');
      
    $result = $this->_db->fetchAll($query);
    $artists = new Jkl_List();

    foreach ($result as $params) {
      $params['aka'] = $this->_getAka($params['art_id']);
      $params['albumCount'] = Model_Album_Api::getInstance()->getArtistsAlbumsCount($params['art_id']);
      $artists->add(new Model_Artist_Container($params));
    }

    return $artists;
  }

  public function getNewest($limit = 20)
  {
    $limit = intval($limit);
    $query = 'SELECT * ' . 
    'FROM artists ' .
    'ORDER by added DESC ' . 
    'LIMIT ' . $limit;

    return $this->getList($query);
  }

  public function find($id, $full = false)
  {
    $id = intval($id);
    $query = 'select *, id as art_id from artists where id=' . $id;
    $result = $this->_db->fetchAll($query);
    $params = $result[0];
    
    if ($full) {
      // photos
      $params['photos'] = Model_Image_Api::getInstance()->getArtistPhotos($id);

      // also known as
      $params['aka'] = $this->_getAka($id);

        // band members
      $params['members'] = $this->_getMembers($id);

      // member of bands
      $params['projects'] = $this->_getProjects($id);

      // city
      $params['cities'] = Model_City_Api::getInstance()->getArtistCities($id);
    } // full

    $item = new Model_Artist_Container($params, $full);
    return $item;
  }

  private function _getMembers($id)
  {
    $id = intval($id);
    $query = 'SELECT t1.name, t1.id as art_id, t2.insince, t2.awaysince FROM artists AS t1, band_lookup AS t2 ' .
              'WHERE (t1.id=t2.artistid AND t2.bandid=' . $id . ') ORDER BY t1.name';
    return $this->getList($query);
  }

  private function _getProjects($id)
  {
    $id = intval($id);
    $query = 'SELECT t1.name, t1.id as art_id, t2.insince AS since, t2.awaysince AS till FROM artists AS t1, band_lookup AS t2 ' .
              'WHERE (t1.id=t2.bandid AND t2.artistid=' . $id . ') ORDER BY t1.name';
    return $this->getList($query);
  }

  private function _getAka($id)
  {
    $query = 'SELECT t1.altname FROM altnames_lookup AS t1 ' .
              'WHERE (t1.artistid=' . $id . ') ORDER BY t1.altname';
    $result = $this->_db->fetchAll($query);
    $aka = new Jkl_List('Also known as list');
    foreach ($result as $key => $value) {
      $aka->add($value['altname']);
    }
    return $aka;
  }

  public function getSongFeaturing($id)
  {
    $id = intval($id);
    $query = 'SELECT t1.id as art_id, t3.feattype ' . 
      'FROM artists t1, feature_lookup t2, feattypes t3 ' . 
      'WHERE (t2.artistid=t1.id AND t3.id=t2.feattype AND t2.songid="' . $id . '") ' . 
      'ORDER BY t1.name';
    $result = $this->_db->fetchAll($query);
    $featuring = new Jkl_List();
    foreach ($result as $params) {
      $artist = $this->find($params['art_id']);
      $artist->featType = $params['feattype'];
      $featuring->add($artist);
      }

    return $featuring;
  }

  public function getSongMusic($id)
  {
    $id = intval($id);
    $query = 'SELECT *, t1.id as art_id ' .
      'FROM artists AS t1, music_lookup AS t2 ' .
      'WHERE (t1.id=t2.artistid AND t2.songid=' . $id . ') ' .
      'ORDER BY t1.name';

    return $this->getList($query);
  }
  
  public function getSongScratch($id)
  {
    $id = intval($id);
    $query = 'SELECT *, t1.id as art_id ' .
      'FROM artists AS t1, scratch_lookup AS t2 ' .
      'WHERE (t1.id=t2.artistid AND t2.songid=' . $id . ') ' .
      'ORDER BY t1.name';

   return $this->getList($query);
  }

  public function getSongArtist($id)
  {
    $id = intval($id);
    $query = 'SELECT *, t1.id as art_id ' .
      'FROM artists AS t1, artist_lookup AS t2 ' .
      'WHERE (t1.id=t2.artistid AND t2.songid=' . $id . ') ' .
      'ORDER BY t1.name';

   return $this->getList($query);
  }
  
  public function getWithMostProjectAlbums($limit = 10)
  {
    $limit = intval($limit);
     $query = "SELECT t4.`id` AS art_id, t4.`name`, count(*) AS albumCount
     FROM albums t1, album_artist_lookup t2, band_lookup t3, artists t4
     WHERE (t3.`bandid`=t2.`artistid` AND t3.`artistid`=t4.`id` AND t1.`id`=t2.`albumid`)
     GROUP BY t4.id
     ORDER BY albumCount DESC" .
     (($limit != null)?' LIMIT ' . $limit:'');

    return $this->getList($query);
  }
  
  public function getWithMostSoloAlbums($limit = 10)
  {
    $limit = intval($limit);
    
     $query = "SELECT t3.`id` AS art_id, t3.`name`, count(*) AS albumCount
     FROM albums t1, album_artist_lookup t2, artists t3
     WHERE (t3.`id`=t2.`artistid` AND t1.`id`=t2.`albumid` AND t3.`id`<>190)
     GROUP BY t3.id
     ORDER BY albumCount DESC" .
     (($limit != null)?' LIMIT ' . $limit:'');

    return $this->getList($query);
  }
  
  public function getRecentlyAdded($limit = 10)
  {
    $limit = intval($limit);
    $query = "SELECT t1.id AS art_id, t1.`name`
    FROM artists t1
    ORDER BY t1.`added` DESC" . 
    (($limit != null)?' LIMIT ' . $limit:'');

    return $this->getList($query);
  }

  public function updateView($id)
  {
    $id = intval($id);
    $query = 'UPDATE artists SET viewed=viewed+1 WHERE id=' . $id;
    $this->_db->query($query);
  }
  
  public function getLikeCount($like = '')
  {
    $like = Jkl_Db::escape($like);
    $query = "SELECT count(*) as count
              FROM artists AS t1
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
    $query = "SELECT t1.id AS art_id, t1.name AS art_name
              FROM artists t1
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
    $query = "SELECT t1.id AS art_id, t1.name AS art_name
              FROM artists t1
              WHERE (t1.id = '" . $id . "');";
    $result = $this->_db->fetchAll($query);
    if (!empty($result[0])) {
      return $result[0];
    }
    return false;
  }
}