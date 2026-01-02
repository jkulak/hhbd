<?php

/**
 * Comment container
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package default
 **/

class Model_Comment_Container
{
  const TYPE_ARTIST = 'p';
  const TYPE_ALBUM = 'a';
  const TYPE_NEWS = 'n';
  const TYPE_SONG = 's';
  const TYPE_LABEL = 'l';
  
  private $_commentType = array(
    'p' => Model_Comment_Container::TYPE_ARTIST,
    'a' => Model_Comment_Container::TYPE_ALBUM,
    'n' => Model_Comment_Container::TYPE_NEWS,
    's' => Model_Comment_Container::TYPE_SONG,
    'l' => Model_Comment_Container::TYPE_LABEL);
  
  public $id;
  public $content;
  public $author;
  public $authorId;
  public $added;
  public $type;
  
  private $_updatedBy;
  private $_updated;
  private $_authorIp;
  
  
  function __construct($params)
  {
    $this->id = $params['com_id'];
    $this->content = $params['com_content'];
    $this->author = $params['com_author'];
    $this->added = $params['com_added'];
    $this->authorId = $params['com_author_id'];
    $this->type = $this->_commentType[$params['com_object_type']];
    
    $this->_updatedBy = $params['com_updated_by'];
    $this->_updated = $params['com_updated'];
    $this->_authorIp = $params['com_author_ip'];
  }
}