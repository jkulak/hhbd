<?php
/**
 * Song Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package hhbd
 **/

class Model_Song_Api extends Jkl_Model_Api
{
  
  static private $_instance;
  
  const LYRICS_ACTION_ADD = "add";
  const LYRICS_ACTION_EDIT = "edit";
  const LYRICS_ACTION_DELETE = "delete";
  
  const MINIMUM_LYRICS_LENGTH = 20;
  
  // private $_artistTypes = array(
  //   'add' => Model_Song_Container::LYRICS_ACTION_ADD,
  //   'edit' => Model_Song_Container::LYRICS_ACTION_EDIT,
  //   'delete' => Model_Song_Container::LYRICS_ACTION_DELETE);
  
  
  
  /**
   * Singleton instance
   *
   * @return Model_Song_Api
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
    $result = $this->_db->fetchAll($query);;
    $list = new Jkl_List(); 
    foreach ($result as $params) {
      $list->add(new Model_Song_Container($params));
    }
    return $list;
  }
  
  public function find($id, $full = false)
  {
    $id = intval($id);
    $query = 'select *, id as song_id from songs where id=' . $id;
    $result = $this->_db->fetchAll($query);
    $params = $result[0];
    
    $params['featuring'] = Model_Artist_Api::getInstance()->getSongFeaturing($id);
    $params['music'] = Model_Artist_Api::getInstance()->getSongMusic($id);
    $params['scratch'] = Model_Artist_Api::getInstance()->getSongScratch($id);
    $params['artist'] = Model_Artist_Api::getInstance()->getSongArtist($id);
    
    $params['featured'] = Model_Album_Api::getInstance()->getSongAlbums($id, null);

    $max = isset($params['featured']->items) ? count($params['featured']->items) : 0;
    if ($max > 0) {
      for ($i=0; $i < $max; $i++) { 
        if ($params['featured']->items[$i]->artist->name != 'V/A') {
          $albumArtist = $params['featured']->items[$i]->artist;
          // we set artists for this song, so we can exit
          break(1);
        }
      }
    } else {
      // song is not featured on any album for some reason? send notification email
      Zend_Registry::get('Logger')->info('Song ' . $id . ' is not featured on any album.');
    }
    
    if (!isset($albumArtist)) {
      if ($max > 0) {
        $albumArtist = new Model_Artist_Container(array('name' => $params['featured']->items[0]->title, 'art_id' => $params['featured']->items[0]->id));
      }
    }
    
    $params['albumArtist'] = $albumArtist;

    // assign album artist to a song, if it doesnt have artist assigned
    if (!isset($params['artist']->items) || count($params['artist']->items) < 1) {
      $params['artist']->add($albumArtist);
    }
    
    if ($params['youtube_url'] === null and $full) {
      $searchTerms = (isset($albumArtist)?$albumArtist->name . ' ':'') . $params['title'];
      $rep = array('/', '&', '?', '-');
      $searchTerms = str_replace($rep, ' ', $searchTerms);
      $url = $this->_getYouTubeUrl($searchTerms);
      $query = 'UPDATE songs SET youtube_url="' . $url . '" WHERE id=' . $id;
      $this->_db->query($query);
      $params['youtube_url'] = $url;
      // Zend_Registry::get('Logger')->info('Save YouTube ' . $params['title'] . '(' . $id . '): ' . $url); 
    }

    $item = new Model_Song_Container($params);
    return $item;
  }
  
  public function getTracklist($id)
  {
    $id = intval($id);
    $query = 'SELECT t1.id as song_id, t2.track ' . 
        'FROM songs AS t1, album_lookup AS t2 ' .
        'WHERE (t1.id=t2.songid AND t2.albumid=' . $id . ') ' . 
        'ORDER BY t2.track';
    $result = $this->_db->fetchAll($query);
    $tracklist = new Jkl_List();
    foreach ($result as $params) {  
      $song = $this->find($params['song_id'], false);
      if (strlen($params['track']) > 2) {
        $song->track = substr($params['track'], 0, 1) . '-' . substr($params['track'], 1, 2);
      }
      else {
        $song->track = $params['track'];
      }
      $tracklist->add($song);
    }
    return $tracklist;
  }

  public function getAlbumDuration($id)
  {
    $id = intval($id);
    $query = 'SELECT sum(t1.length) as duration ' .
      'FROM songs AS t1, album_lookup AS t2 ' .
      'WHERE (t2.songid=t1.id AND t2.albumid=' . $id . ')';
    if (empty($duration)) {
      $result = $this->_db->fetchAll($query);
      if ($result[0]['duration'] > 0) {
        $duration = sprintf( "%02.2d:%02.2d", floor( $result[0]['duration'] / 60 ), $result[0]['duration'] % 60 );
      }
      else
      {
        $duration = 0;
      }
    }
    return $duration;
  }

  public function getMostPopularByArtist($id, $limit = 10)
  {
    $id = intval($id);
    $limit = intval($limit);
    $query = 'SELECT *, t1.id as song_id
              FROM songs t1, artist_lookup t2, artists t3
              WHERE (t1.id=t2.songid AND t2.artistid=t3.id AND t3.id=' . $id . ')
              ORDER BY t1.viewed DESC
              ' . (($limit)?'LIMIT ' . $limit:'');
    return $this->_getList($query);    
  }
  
  public function getMostPopular($limit = 10)
  {
    $limit = intval($limit);
    $query = 'SELECT *, t1.id as song_id
              FROM songs t1
              ORDER BY t1.viewed DESC
              ' . (($limit)?'LIMIT ' . $limit:'');
    return $this->_getList($query);    
  }

  /**
   * YouTube API v2 (Zend_Gdata_YouTube) was deprecated and removed.
   * This method now returns false. YouTube URLs should be added manually via backoffice.
   */
  private function _getYouTubeUrl($searchTerms = 'polski hip-hop')
  {
    // YouTube API v2 is no longer available
    return false;
  }
  
  public function getLike($like, $limit = 25, $page = 1)
  {
    $like = Jkl_Db::escape($like);
    $limit = intval($limit);
    $page = intval($page - 1);
    $page = ($page<1)?0:$page;
    
    $query = "SELECT *, t1.id as song_id
              FROM songs t1
              WHERE t1.title LIKE '%$like%'
              ORDER BY t1.viewed DESC" .
              (($limit != null)?' LIMIT ' . $limit:'') . 
              ' OFFSET ' . ($page*$limit);
    return $this->_getList($query);    
  }
  
  public function getLikeCount($like = '')
  {
    $like = Jkl_Db::escape($like);
    $query = "SELECT count(*) as count
              FROM songs AS t1
              WHERE t1.title LIKE '%$like%'"; 
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
    $query = "SELECT t1.id AS sng_id, t1.title AS sng_title
              FROM songs t1
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
    $query = "SELECT t1.id AS sng_id, t1.title AS sng_title
              FROM songs t1
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
    $query = 'UPDATE songs SET viewed=viewed+1 WHERE id=' . $id;
    $this->_db->query($query);
  }
  
  /**
   * Saves songs lyrics to database
   *
   * @return number of affected rows (0 - no changes made, 1 - changes saved)
   * @author Kuba
   **/
  public function saveLyrics($songId, $lyrics, $userId, $action = self::LYRICS_ACTION_EDIT)
  {
    if (strlen($lyrics) < self::MINIMUM_LYRICS_LENGTH) {
      $action = self::LYRICS_ACTION_DELETE;
    }
    
    $id = intval($songId);
    $lyrics = addslashes($lyrics);
    $userId = intval($userId);
    
    $query = 'UPDATE songs SET lyrics="' . $lyrics . '" WHERE id=' . $id;
    $result = $this->_db->query($query);
    
    // if 1 row was updated save information who did the lyrics editing
    if ($result->rowCount() == 1) {
      $query = 'INSERT INTO hhb_user_lyrics_edit
        SET ule_lyrics_id="' . $songId . '", ule_user_id="' . $userId . '", ule_action_type="' . $action . '", ule_lyrics="' . $lyrics . '", ule_action_timestamp="' . date("Y-m-d H:i:s") . '"';
      $subResult = $this->_db->query($query);
    }
    
    /*
      TODO 2011-02-02 flush memcached for this song here
    */

    return $result->rowCount();
  }
  
  /**
  * Returns list of most recent songs
  * used for: sitemaps
  **/
  public function getRecent($limit = 20)
  {    
    $limit = intval($limit);
    $query = "SELECT *, t1.id AS song_id, t1.title AS sng_title
              FROM songs t1
              ORDER BY t1.added DESC" .
              (($limit != null)?' LIMIT ' . $limit:''); 
    return $this->_getList($query);
  }

  /**
   * getArtists
   * 
   * returns array with objects of songs artists
   **/
  public function getArtists($id)
  {
    
    // skladanka, gdzie chcemy wziac artist (album artist mamy v/a wtedy)
    // plyta producencka, gdzie chcemy wziac feat
    // normalna plyta gdzie wykonawca jest autor plyty (ale nie mamy jak odroznic od producenckiej)
    
    // moze jako wykonawce traktowac zawsze album artist, ale dawac w nawiasie feat ?
    // hhbd.pl/Dj 600v/_/Merctedes (feat. Tede),4581.html
    // hhbd.pl/VA/Letnie hity 2010/Merctedes (feat. Tede),4581.html
    $artists = new Jkl_List();
    //set feat as artist
    $featuring = Model_Artist_Api::getInstance()->getSongFeaturing($id);
    foreach ($featuring->items as $key => $value) {
      $artists->add($value);
    }
    
    //if empty
    //get list of albums
    // seat first album artist not v/a as artist
    if (count($artists->items) < 1) {
      $featured = Model_Album_Api::getInstance()->getSongAlbums($id, null);
      foreach ($featured->items as $key => $value) {
        if ($value->artist->name != 'V/A') {
          $artists->add($value->artist);
        }      
      }
    }
    
    // if still empty
    // set song's artist
    if (count($artists->items) < 1) {
      $artist = Model_Artist_Api::getInstance()->getSongArtist($id); 
      foreach ($featured->items as $key => $value) {
        $artists->add($value);
      }
    }
    return $artists;  
  }

  /**
   * Increases youtube_url_flag number for given song id
   */
  public function flagVideo($id)
  {
    $id = intval($id);
    $query = 'UPDATE songs SET `youtube_url_flag`=`youtube_url_flag`+1 WHERE id="' . $id . '"';
    // echo $query;
    $this->_db->query($query);
  }
}