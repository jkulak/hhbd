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
  // What #61 added: the file's size, and what a licence asks to be shown next to the photo
  public $width;
  public $height;
  public $licence;
  public $licenceUrl;
  public $credit;
  public $modified = false;
  public $description;

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
    $this->width = !empty($params['width']) ? (int) $params['width'] : null;
    $this->height = !empty($params['height']) ? (int) $params['height'] : null;
    $this->licence = !empty($params['licence']) ? $params['licence'] : null;
    $this->licenceUrl = !empty($params['licence_url']) ? $params['licence_url'] : null;
    $this->credit = !empty($params['credit']) ? $params['credit'] : null;
    $this->modified = !empty($params['modified']);
    $this->description = !empty($params['description']) ? $params['description'] : null;
  }

  /*
  * The caption a CC licence asks for, as HTML: "Fot. <author>, <licence> (zmodyfikowane)", the
  * author linked to the photo's source page and the licence to its text when they are known;
  * null for a photo with neither author nor licence.
  */
  public function getCaption()
  {
    if (null === $this->credit && null === $this->licence) {
      return null;
    }
    $link = function ($text, $url) {
      $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
      return $url ? '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="nofollow">' . $text . '</a>' : $text;
    };
    $parts = array();
    if (null !== $this->credit) {
      $parts[] = $link($this->credit, $this->sourceUrl);
    }
    if (null !== $this->licence) {
      $parts[] = $link($this->licence, $this->licenceUrl);
    }
    return 'Fot. ' . implode(', ', $parts) . ($this->modified ? ' (zmodyfikowane)' : '');
  }
}