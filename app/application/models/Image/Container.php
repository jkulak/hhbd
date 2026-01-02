<?php

/**
 * Image
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 11 October, 2010
 * @package default
 **/

class Model_Image_Container
{
  
  public $id;
  public $filename;
  public $source = null;
  public $sourceUrl = null;
  public $isMain = false;
  public $url;
    
  function __construct($params)
  {
    $this->id = (isset($params['id']))?$params['id']:null;
    $this->filename = (isset($params['filename']))?$params['filename']:null;
    $this->url = isset($params['url'])?$params['url']:null;
    $this->source = isset($params['source'])?$params['source']:null;
    $this->sourceUrl = isset($params['sourceurl'])?$params['sourceurl']:null;
    if (isset($params['main'])) {
      $this->isMain = ($params['main'] == 'y');
    }
    
  }
}