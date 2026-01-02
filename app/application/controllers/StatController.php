<?php

class StatController extends Zend_Controller_Action
{
  
  public function init()
  {
    $this->params = $this->getRequest()->getParams();
  }

  public function indexAction()
  {
    
    $type = $this->params['type'];
    $id = intval($this->params['id']);

    if ($id < 1 OR (!in_array($type, array('album', 'artist', 'label', 'song', 'news')))) {
      $this->_helper->json(array('result' => 'aj ti, aj ti!!! :)'));
    }
    
    switch ($this->params['type']) {
      case 'album':
        $result = Model_Album_Api::getInstance()->updateView($this->params['id']);
        break;
      case 'artist':
        $result = Model_Artist_Api::getInstance()->updateView($this->params['id']);
        break;
      case 'label':
        $result = Model_Label_Api::getInstance()->updateView($this->params['id']);
        break;
      case 'song':
        $result = Model_Song_Api::getInstance()->updateView($this->params['id']);
        break;
      case 'news':
        $result = Model_News_Api::getInstance()->updateView($this->params['id']);
        break;
      default:
        break;
      }
    $this->_helper->json(array('result' => 'true'));
    
  }
}