<?php

/**
 * News Container
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 23 Decemnber, 2010
 * @package default
 **/

class Model_News_Container
{
  public $title = null;
  public $content = null;
  public $attachment = null;
  public $addedBy = null;
  public $added = null;
  public $updatedBy = null;
  public $updated;
  
  function __construct($params, $full = false)
  {
    $configApp = Zend_Registry::get('Config_App');
    
    $this->id = $params['nws_id'];
    $this->title = $params['nws_title'];
    $this->content = ($full)?$params['nws_content']:Jkl_Tools_String::trim_str(strip_tags($params['nws_content']), 200);
    if (!empty($params['nws_attachment_url'])) {
      $this->attachment = new Model_Image_Container(array('url' => $configApp['paths']['news']['image'] . $params['nws_attachment_url']));
    }
    $this->added = $params['nws_added'];
    $this->addedNormalized = Jkl_Tools_Date::getNormalDate($params['nws_added']);
    $this->url = Jkl_Tools_Url::createUrl($this->title);
  }
  
}