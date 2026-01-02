<?php

class NewsController extends Zend_Controller_Action
{
  
  public function init()
  {
    $this->view->headMeta()->setName('keywords', 'newsy,aktualności,wiadomości,polski,hip-hop');
    $this->view->headMeta()->setName('description', 'Najświeższe aktualności ze światka polskiego hip-hopu');
    $this->params = $this->getRequest()->getParams();
  }

  public function indexAction()
  {
  }
  
  public function viewAction()
  {
    $newsId = $this->params['id'];
    $news = Model_News_Api::getInstance()->find($newsId, true);
    
    $this->view->news = $news;
    
    $this->view->recentNews = Model_News_Api::getInstance()->getRecent(25);
    $this->view->comments = Model_Comment_Api::getInstance()->getComments($newsId, Model_Comment_Container::TYPE_NEWS);
    
    $this->view->headTitle()->headTitle($news->title, 'PREPEND');
    $this->view->headMeta()->setName('description', $news->content);
  }
}