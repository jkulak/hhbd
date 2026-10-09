<?php
/**
 * Image Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/

class Model_Image_Api extends Jkl_Model_Api
{  
  static private $_instance;
  private $_appConfig;
  
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
  
  /*
  * $db and $appConfig are for tests; everything else takes the shared connection and config
  */
  function __construct($db = null, $appConfig = null) {
    $this->_appConfig = (null === $appConfig) ? Zend_Registry::get('Config_App') : $appConfig;
    if (null === $db) {
      parent::__construct();
    } else {
      $this->_db = $db;
    }
  }

  /*
  * Adds a photo of an artist (#61) and keeps exactly one main photo per artist: the new one when
  * $main says so or when the artist has none yet. $photo holds the columns: filename, width,
  * height, sha256, mime, description, source, sourceurl, licence, licence_url, credit, modified,
  * addedby (the import's user, #63; 0 when a person added it before there was one).
  * Returns the new row's id.
  */
  public function addArtistPhoto($artistId, array $photo, $main = false)
  {
    $artistId = (int) $artistId;
    if ($artistId < 1 || empty($photo['filename'])) {
      throw new InvalidArgumentException('A photo needs an artist and a file');
    }
    $hasMain = $this->_db->fetchAll('SELECT id FROM artists_photos WHERE artistid = ? AND main = ?', array($artistId, 'y'));
    $main = $main || empty($hasMain);
    if ($main && !empty($hasMain)) {
      $this->_db->query('UPDATE artists_photos SET main = ? WHERE artistid = ?', array('n', $artistId));
    }
    $columns = array('filename', 'width', 'height', 'sha256', 'mime', 'description', 'source', 'sourceurl', 'licence', 'licence_url', 'credit', 'modified', 'addedby');
    $bind = array($artistId, $main ? 'y' : 'n');
    foreach ($columns as $column) {
      $default = in_array($column, array('description', 'source', 'sourceurl'), true) ? '' : null;
      $bind[] = isset($photo[$column]) ? $photo[$column] : (in_array($column, array('modified', 'addedby'), true) ? 0 : $default);
    }
    $this->_db->query(
      'INSERT INTO artists_photos (artistid, main, ' . implode(', ', $columns) . ') VALUES (?, ?' . str_repeat(', ?', count($columns)) . ')',
      $bind
    );
    return (int) $this->_db->lastInsertId();
  }
  
  public function getArtistPhoto($id)
  {
    $query = 'SELECT * FROM artists_photos WHERE (artistid=' . $id . ' AND main="y")';
    $result = $this->_db->fetchAll($query);
    $pictures = new Jkl_List('Picture list');
    if (sizeof($result) != 0) {
      foreach ($result as $key => $value) {
        $value['url'] = $this->_appConfig['paths']['artistPhotoPath'] . $value['filename'];
        $pictures->add(new Model_Image_Container($value));
      }
    } else {
      $params['url'] = $this->_appConfig['paths']['artistPhotoPath'] . 'no.png';
      $pictures->add(new Model_Image_Container($params));
    }
    return $pictures;
  }

  public function getArtistPhotos($id)
  {
    // The main photo first, then the others in the order they came (#61).
    $query = 'SELECT * FROM artists_photos WHERE (artistid=' . intval($id) . ') ORDER BY main, id';
    $result = $this->_db->fetchAll($query);
    $pictures = new Jkl_List('Picture list');
    if (sizeof($result) != 0) {
      foreach ($result as $key => $value) {
        $value['url'] = $this->_appConfig['paths']['artistPhotoPath'] . $value['filename'];
        $pictures->add(new Model_Image_Container($value));
      }
    } else {
      $params['url'] = $this->_appConfig['paths']['artistPhotoPath'] . 'no.png';
      $pictures->add(new Model_Image_Container($params));
    }
    return $pictures;
  }
}