<?php

class SitemapController extends Zend_Controller_Action
{
  
  public function init()
  {
    $this->view->hourAgo = date('c', strtotime('-1 hour'));
    $this->view->dayAgo = date('c', strtotime('-1 day'));
    
    $this->_helper->layout->setLayout('sitemap');
    
    $this->params = $this->getRequest()->getParams();
  }

  public function indexAction()
  {
    echo 'sdfsd';
  }
  
  public function viewAction()
  {
    $urls = array();
    switch ($this->params['type']) {
      case 'albums':
        // gets album list sorted by release date
        $list = Model_Album_Api::getInstance()->getAlbumsSitemap();
        foreach ($list->items as $key => $value) {
          $urls[] = $this->view->url(array('id' => $value->id, 'seo' => $value->artist->url . ' - ' . $value->url), 'album');
        }
        break;

      case 'news':
        $list = Model_News_Api::getInstance()->getRecent(10000, false);
        foreach ($list->items as $key => $value) {
          $urls[] = $this->view->url(array('id' => $value->id, 'seo' => $value->url), 'news');
        }
        break;

      case 'artists':
        $list = Model_Artist_Api::getInstance()->getRecentlyAdded(10000);
        foreach ($list->items as $key => $value) {
          $urls[] = $this->view->url(array('id' => $value->id, 'seo' => $value->url), 'artist');
        }
        break;
      
      case 'songs':
        $list = Model_Song_Api::getInstance()->getRecent(10000);
        foreach ($list->items as $key => $value) {
          $urls[] = $this->view->url(array('id' => $value->id, 'seo' => $value->title), 'song');
        }
        break;

      case 'labels':
        $list = Model_Label_Api::getInstance()->getRecent(10000);
        foreach ($list->items as $key => $value) {
          $urls[] = $this->view->url(array('id' => $value->id, 'seo' => $value->name), 'label');
        }
        break;

      default:
        # code...
        break;
    }
    $this->view->urls = $urls;
  }
}