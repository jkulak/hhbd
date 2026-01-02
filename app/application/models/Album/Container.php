<?php

/**
 * Album
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 11 October, 2010
 * @package default
 **/

class Model_Album_Container
{
  
  public $id;
  public $title;
  public $artist;
  public $releaseDate;
  public $cover;
  public $autoDescription = null;
  
  function __construct($params, $full = false)
  {
    // print_r($params); die();
    
    $configApp = Zend_Registry::get('Config_App');

    $this->id = $params['alb_id'];
    $this->title = $params['title'];
    
    if (!empty($params['art_id'])) {
      $artistApi = Model_Artist_Api::getInstance();
      $this->artist = $artistApi->find($params['art_id']);
    }    
    
    if (!empty($params['lab_id'])) {
      $this->label = Model_Label_Api::getInstance()->find($params['lab_id']);
      if ($this->label->name == 'BRAK') $this->label->name = null;
    } else {
      $this->label = null;
    }
    
    if (!empty($params['legal'])) {
      $this->legal = ($params['legal']=='y')?true:false;
    }
    
    $this->releaseDate = $params['year'];
    $this->year = substr($params['year'], 0, 4);
    $this->releaseDateNormalized = Jkl_Tools_Date::getNormalDate($this->releaseDate);

    $this->catalogNumber = (!empty($params['catalog_cd'])?$params['catalog_cd']:null);
    
    
    if (!empty($params['epfor'])) {
      $this->epFor = $params['epfor'];
    }
    
    if (!empty($params['singiel'])) {
      $this->ep = $params['singiel'];
    }

    // TODO: users api
    if (!empty($params['alb_addedby'])) $this->addedBy = $params['alb_addedby'];
    if (!empty($params['alb_added'])) $this->added = $params['alb_added'];
    if (!empty($params['alb_viewed'])) $this->views = $params['alb_viewed'];
    if (!empty($params['updated'])) $this->updated = $params['updated'];
    
    if (!empty($params['cover'])) {
      $this->cover = $configApp['paths']['albumCoverPath'] . $params['cover'];
      $this->thumbnail = $configApp['paths']['albumThumbnailPath'] . substr($params['cover'], 0, -4) . $configApp['paths']['albumThumbnailSuffix'];
    }
    else
    {
      $this->cover = $configApp['paths']['albumCoverPath'] . 'cd.png';
      $this->thumbnail = $configApp['paths']['albumThumbnailPath'] . 'cd.png';
    }
    
    if (!empty($params['rating'])) {
      $this->rating = number_format($params['rating'], 1);
    } else {
      $this->rating = '--';
    }
    if (!empty($params['updated'])) {
      $this->updated = $params['updated'];
    }
    
    if (!empty($params['status'])) {
      $this->status = $params['status'];
    }
    
    if ($full) {
      $this->tracklist = $params['tracklist'];
      $this->description = $params['description'];
      $this->eps = $params['eps'];
      $this->duration = $params['duration'];
      $this->voteCount = $params['votecount'];
    }
    
    $this->url = Jkl_Tools_Url::createUrl($this->title);
  }
  
  /**
   * Checks if album is announced, or already released
   */
  public function isAnnounced() {
    return ($this->releaseDate >= date('Y-m-d'));
  }
}
     // [premier] => 
     // [media_mc] => 1
     // [catalog_mc] => 
     // [media_cd] => 1
     // [media_lp] => 0
     // [catalog_lp] => 
     // [artistabout] => 
     // [notes] => 