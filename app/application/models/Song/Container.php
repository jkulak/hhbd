<?php

/**
 * Song
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package default
 **/

class Model_Song_Container
{ 
  public $lyrics = '';
  
  function __construct($params, $full = false)
  {
    $this->id = $params['song_id'];
    $this->title = $params['title'];
    
    $this->track = (!empty($params['track'])?$params['track']:null);
    
    if (!empty($params['length'])) {
      $this->duration = sprintf( "%02.2d:%02.2d", floor( $params['length'] / 60 ), $params['length'] % 60 );
    }
    else {
      $this->duration = null;
    }
    $this->bpm = $params['bpm'];
    
    if (!empty($params['featuring'])) {
      $this->featuring = $params['featuring'];
    }
    
    if (!empty($params['music'])) {
      $this->music = $params['music'];
    }
    
    if (!empty($params['scratch'])) {
      $this->scratch = $params['scratch'];
    }
    
    if (!empty($params['artist'])) {
      $this->artist = $params['artist'];
    }
    
    if (!empty($params['youtube_url'])) {
      $this->youTubeUrl = $params['youtube_url'];
    }

    $this->youTubeUrlFlag = $params['youtube_url_flag'];

    if (!empty($params['featured'])) {
      $this->featured = $params['featured'];
    }
    
    if (!empty($params['lyrics'])) {
      $this->lyrics = $params['lyrics'];
    }
    
    if (!empty($params['albumArtist'])) {
      $this->albumArtist = $params['albumArtist'];
    }
  }
  
  public function url()
  {
    return Jkl_Tools_Url::createUrl($this->title);
  }
}