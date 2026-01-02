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
class Model_Album_Api extends Jkl_Model_Api
{
  
  static private $_instance;
  
  /**
   * Singleton instance
   *
   * @return Model_Album_Api
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
    $albums = new Jkl_List(); 
    foreach ($result as $params) {
      $albums->add(new Model_Album_Container($params));
    }
    return $albums;
  }
  
  public function find($id, $full = false)
  {
    $id = intval($id);
    $query = "SELECT *, t1.id as alb_id, t1.labelid AS lab_id, t1.epfor as epforid, t1.added as alb_added, t1.addedby as alb_addedby, t1.viewed as alb_viewed, t3.id as art_id " . 
    "FROM albums t1, album_artist_lookup t2, artists t3 " . 
    "WHERE (t3.id=t2.artistid AND t2.albumid=t1.id AND t1.id='" . $id . "')";
    $result = $this->_db->fetchAll($query);
    $params = $result[0];    
    if ($full) { 
      $params['tracklist'] = Model_Song_Api::getInstance()->getTracklist($id);
      $params['eps'] = $this->getEps($id);
      if (!empty($params['epforid'])) $params['epfor'] = $this->getEpFor($params['epforid']);
      $params['duration'] = Model_Song_Api::getInstance()->getAlbumDuration($id);
      $params['votecount'] = Model_Rating_Api::getInstance()->getAlbumVoteCount($id);
      $params['rating'] = Model_Rating_Api::getInstance()->getAlbumRating($id);
    }
    $item = new Model_Album_Container($params, $full);
    return $item;
  }

  private function getEpFor($id)
  {
    $id = intval($id);
    return Model_Album_Api::getInstance()->find($id);
  }
  
  private function getEps($id)
  {
    $id = intval($id);
    $query = 'SELECT id FROM albums WHERE epfor=' . $id . ' ORDER BY year DESC';
    $result = $this->_db->fetchAll($query);
    $eps = new Jkl_List();
    $albumApi = Model_Album_Api::getInstance();
    foreach ($result as $params) {  
      $ep = $albumApi->find($params['id']);
      $eps->add($ep);
    }
    return $eps;
  }

  public function getLike($like = '', $limit = 20, $page = 1)
  {
    $like = Jkl_Db::escape($like);
    $limit = intval($limit);
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
      'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3, labels AS t4 ' .
      'WHERE (t3.title LIKE "%' . $like . '%" AND t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id=t3.labelid) ' . 
      'ORDER BY t3.viewed DESC' . 
      (($limit != null)?' LIMIT ' . $limit:'') . 
      ' OFFSET ' . ($page*$limit);
    return $this->getList($query);
  }
  
  public function getLikeCount($like = '')
  {
    $like = Jkl_Db::escape($like);
    $query = "SELECT count(*) as count
              FROM albums AS t1
              WHERE t1.title LIKE '%$like%'"; 
    $result = $this->_db->fetchAll($query);
    return intval($result[0]['count']);
  }
  /**
   * Gets list of popular albums by views count, including incomming albums
   *
   * @param integer $count Number of albums to be returned
   * @return Jkl_List
   * @author Kuba
   */
  public function getPopular($count = 20)
  {
    $count = intval($count);
    $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
      'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3, labels AS t4 ' .
      'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id=t3.labelid) ' . 
      'ORDER BY t3.viewed DESC ' . 
      'LIMIT ' . $count;
    return $this->getList($query);
  }
  
  public function getBest($count = 10)
  {
    $count = intval($count);
    $query = 'SELECT *, t1.id AS alb_id, t2.rating AS rating, t1.title, t3.artistid AS art_id ' . 
      'FROM albums t1, ratings_avg t2, album_artist_lookup t3 ' . 
      ' WHERE (t1.id=t2.albumid AND t3.albumid=t1.id) ' . 
      'ORDER BY t2.rating DESC ' .
      'LIMIT ' . $count;
    return $this->getList($query);
  }

  /**
  * Returns list of released albums sorted by release date, decreasing (from most recent to oldest)
  */
  public function getNewest($count = 20, $page = 1)
  {
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
      'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3, labels AS t4 ' .
      'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id=t3.labelid AND t3.year' . '<="' . date('Y-m-d') . '") ' . 
      'ORDER BY t3.year DESC ' . 
      'LIMIT ' . $count . ' ' . 
      'OFFSET ' . ($page*$count);
    return $this->getList($query);
  }

  public function getAnnounced($count = 20, $page = 1)
  {
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id AS lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
      'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3, labels AS t4 ' .
      'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id=t3.labelid AND t3.year' . '>"' . date('Y-m-d') . '") ' . 
      'ORDER BY t3.year ASC ' . 
      'LIMIT ' . $count . ' ' . 
      'OFFSET ' . ($page * $count);
    return $this->getList($query);
  }
  
  public function getFirstLetters()
  {
    $query = 'SELECT DISTINCT(SUBSTR(title, 1,1)) as name from albums order by name ASC';
    return  $this->_db->fetchAll($query);
  }
  
  public function getArtistsAlbums($id, $exclude = array(), $count = 10, $order = 'viewed') {
    $id = intval($id);
    $count = intval($count);
    // $order in array
    
    $excludeCondition = '';
    if (!empty($exclude)) {
      foreach ($exclude as $key => $value) {
        $excludeCondition .= ' AND t3.id<>' . intval($value) . ' ';
      }
    }    
    $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
      'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3, labels AS t4 ' .
      'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id=t3.labelid AND t1.id="' . $id . '"' . 
      $excludeCondition . 
      ') ' . 
      'ORDER BY t3.' . $order . ' DESC ' . 
      (($count)?'LIMIT ' . $count:'');
    return self::getList($query);
  }
  
  public function getArtistsAlbumsCount($id) {
    $id = intval($id);
    $query = "SELECT count(*) as count
              FROM albums t1, album_artist_lookup t2, band_lookup t3
              WHERE (t3.`bandid`=t2.`artistid` AND t3.`artistid`=$id AND t1.`id`=t2.`albumid`)";
    $result = $this->_db->fetchAll($query);
    $projectAlbums = $result[0]['count'];
    
    $query = "SELECT count(*) as count
              FROM albums t1, album_artist_lookup t2
              WHERE (t1.id=t2.albumid AND t2.artistid=$id);";
    $result = $this->_db->fetchAll($query);
    $albumCount = $result[0]['count'];
    
    return $albumCount + $projectAlbums;
  }
  
  public function getLabelsAlbums($id, $exclude = array(), $count = 10) {
    $id = intval($id);
    $count = intval($count);
    $excludeCondition = '';
    if (!empty($exclude)) {
      foreach ($exclude as $key => $value) {
        $excludeCondition .= ' AND t3.id<>' . intval($value) . ' ';
      }
    }
    $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
      'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3, labels AS t4 ' .
      'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id=t3.labelid AND t4.id="' . $id . '"' . 
      $excludeCondition . 
      ') ' . 
      'ORDER BY t3.viewed DESC ' . 
      'LIMIT ' . $count;
    return $this->getList($query);
  }
  
  public function getAlbumCount()
  {
    $query = 'SELECT count(id) as albumcount FROM albums WHERE (year<="' . date('Y-m-d') . '")';
    $result = $this->_db->fetchAll($query);
    return (int)$result[0]['albumcount'];    
  }

  public function getAnnouncedCount()
  {
    $query = 'SELECT count(id) as albumcount FROM albums WHERE (year>"' . date('Y-m-d') . '")';
    $result = $this->_db->fetchAll($query);
    return (int)$result[0]['albumcount'];    
  }
  
  public function getFeaturingByArtist($id, $limit = 10)
  {
    $id = intval($id);
    $limit = intval($limit);
    $query = 'SELECT DISTINCT(a1.id) as alb_id
              FROM albums a1, songs a2, feature_lookup a3, album_lookup a4
              WHERE (a3.artistid=' . $id . ' AND a3.songid=a2.id AND a2.id=a4.songid AND a1.id=a4.albumid)';
    $albumIds = $this->_db->fetchAll($query);
    if (empty($albumIds)) {
      return false;
    }
    
    $condition = array();
    
    foreach ($albumIds as $key => $value) {
      $condition[] = 't3.albumid=' . $value['alb_id'];
    }
    
    $query = 'SELECT *, t1.id AS alb_id, t2.id AS art_id
              FROM albums t1, artists t2, album_artist_lookup t3
              WHERE (t2.id=t3.artistid AND t1.id=t3.albumid AND (
              ' . implode($condition, ' OR ') . ')
              )' . 
              (($limit != null)?' LIMIT ' . $limit:'');

    return $this->getList($query);
  }
  
  public function getMusicByArtist($id, $limit = 10)
  {
    $id = intval($id);
    $limit = intval($limit);
    $query = 'SELECT DISTINCT(a1.id) as alb_id
              FROM albums a1, songs a2, music_lookup a3, album_lookup a4
              WHERE (a3.artistid=' . $id . ' AND a3.songid=a2.id AND a2.id=a4.songid AND a1.id=a4.albumid)';
    $albumIds = $this->_db->fetchAll($query);
    if (empty($albumIds)) {
      return false;
    }
    
    $condition = array();
    
    foreach ($albumIds as $key => $value) {
      $condition[] = 't3.albumid=' . $value['alb_id'];
    }
    
    $query = 'SELECT *, t1.id AS alb_id, t2.id AS art_id
              FROM albums t1, artists t2, album_artist_lookup t3
              WHERE (t2.id=t3.artistid AND t1.id=t3.albumid AND (
              ' . implode($condition, ' OR ') . ')
              )' . 
              (($limit != null)?' LIMIT ' . $limit:'');

    return $this->getList($query);
  }
  
  public function getScratchByArtist($id, $limit = 10)
  {
    $id = intval($id);
    $limit = intval($limit);
    $query = 'SELECT DISTINCT(a1.id) as alb_id
              FROM albums a1, songs a2, scratch_lookup a3, album_lookup a4
              WHERE (a3.artistid=' . $id . ' AND a3.songid=a2.id AND a2.id=a4.songid AND a1.id=a4.albumid)';
    $albumIds = $this->_db->fetchAll($query);
    if (empty($albumIds)) {
      return false;
    }
    
    $condition = array();
    
    foreach ($albumIds as $key => $value) {
      $condition[] = 't3.albumid=' . $value['alb_id'];
    }
    
    $query = 'SELECT *, t1.id AS alb_id, t2.id AS art_id
              FROM albums t1, artists t2, album_artist_lookup t3
              WHERE (t2.id=t3.artistid AND t1.id=t3.albumid AND (
              ' . implode($condition, ' OR ') . ')
              )' . 
              (($limit != null)?' LIMIT ' . $limit:'');

    return $this->getList($query);
  }
  
  // List of albums that feature a song with given ID
  public function getSongAlbums($id, $limit)
  {
    $id = intval($id);
    $limit = intval($limit);
    $query = "SELECT *, t1.id as alb_id, t3.id as art_id  
              FROM albums t1, album_lookup t2, artists t3, album_artist_lookup t4 
              WHERE (t1.id=t2.albumid AND t2.songid='$id' AND t4.albumid=t1.id AND t4.artistid=t3.id)
              " .
              (($limit != null)?' LIMIT ' . $limit:'');
    return $this->getList($query);
  }
  
  public function getLabelReleases($id, $limit = 10)
  {
    $id = intval($id);
    
    $query = "SELECT *, t1.id AS alb_id, t3.id AS art_id
    FROM albums t1, `album_artist_lookup` t2, `artists` t3
    WHERE (t3.`id`=t2.`artistid` AND t1.`id`=t2.`albumid` AND t1.`labelid`=$id)
    ORDER BY t1.`year` DESC" .
    (($limit != null)?' LIMIT ' . $limit:'');
    return $this->getList($query);
  }
  
  public function redirectFromOld($urlName)
  {
    $urlName = strtolower(strval($urlName));
    if (empty($urlName)) {
      return false;
      // throw exception
    }
    $query = "SELECT t1.id AS alb_id, t1.title AS alb_title, t3.name AS art_name
              FROM albums t1, album_artist_lookup t2, artists t3
              WHERE (t1.id=t2.albumid AND t3.id=t2.`artistid` AND t1.`urlname` = '" . $urlName . "');";
    $result = $this->_db->fetchAll($query);
    if (!empty($result[0])) {
      return $result[0];
    }
    return false;
  }
  
  public function redirectById($id)
  {
    $id = intval($id);
    if (empty($id)) {
      return false;
      // throw exception
    }
    $query = "SELECT t1.id AS alb_id, t1.title AS alb_title, t3.name AS art_name
              FROM albums t1, album_artist_lookup t2, artists t3
              WHERE (t1.id=t2.albumid AND t3.id=t2.`artistid` AND t1.id = '" . $id . "');";
    $result = $this->_db->fetchAll($query);
    if (!empty($result[0])) {
      return $result[0];
    }
    return false;
  }
  
  public function getMain()
  {
    // get artist for the homesite Top Story
  }
  
  public function updateView($id)
  {
    $id = intval($id);
    $query = 'UPDATE albums SET viewed=viewed+1 WHERE id=' . $id;
    $this->_db->query($query);
  }
  
  public function getSitemap()
  {
    $query = "SELECT t1.id AS alb_id, t1.title AS alb_title
              FROM albums t1
              ORDER BY t1.year DESC";
    return $this->getList($query);
  }
  
  /**
   * Function returns list of all albums sorted by added date descending, for sitemap-albums.xml
   *
   * @return JkL_List of Model_Alubm_Container
   * @author Kuba
   **/
   public function getAlbumsSitemap($limit = 10000)
   {
     $limit = intval($limit);
     $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
       'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3, labels AS t4 ' .
       'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id=t3.labelid) ' . 
       'ORDER BY t3.added DESC ' . 
       'LIMIT ' . $limit;
     return $this->getList($query);
   }
}