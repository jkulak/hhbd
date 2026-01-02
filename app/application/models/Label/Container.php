<?php

/**
 * Label container
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 5 December, 2010
 * @package default
 **/

class Model_Label_Container
{
  
  public $id;
  public $name;
    
  function __construct($params, $full = false)
  {
    
    $configApp = Zend_Registry::get('Config_App');
    
    $this->id = $params['lab_id'];
    $this->name = $params['name'];
    $this->url = Jkl_Tools_Url::createUrl($this->name);
    
    $this->albumCount = isset($params['album_count']) ? intval($params['album_count']) : 0;
    $this->website = isset($params['website']) ? strval($params['website']) : null;
    $this->email = isset($params['email']) ? strval($params['email']) : null;
    $this->addres = isset($params['addres']) ? strval($params['addres']) : null;
    $this->description = isset($params['profile']) ? strval($params['profile']) : null;
    if (!empty($params['logo'])) {
       $this->logo = $configApp['paths']['labelLogoPath'] . strval($params['logo']);
    }
    else
    {
      $this->logo = null;
    }
    
    //user api
    //$this->addedBy = isset($params['addedBy']) ? intval($params['addedBy']) : null;
    //user api
    //$this->updatedBy = isset($params['updatedBy']) ? intval($params['updatedBy']) : null;
    
    $this->added = isset($params['added']) ? $params['added'] : null;
    $this->updated = isset($params['updated']) ? $params['updated'] : null;
    
  }
}

/*
[viewed] => 6365
[status] => 0
[hits] => 1545
*/
