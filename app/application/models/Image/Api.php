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
  
  function __construct() {
    $this->_appConfig = Zend_Registry::get('Config_App');
    parent::__construct();
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
    $query = 'SELECT * FROM artists_photos WHERE (artistid=' . $id . ') ORDER BY main';
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