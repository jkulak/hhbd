<?php

class AlbumController extends Zend_Controller_Action
{
  
  public function init()
  {
    $this->view->headMeta()->setName('keywords', 'polski hip-hop, albumy');
    $this->view->headTitle()->headTitle('Albumy', 'PREPEND');
    $this->view->headMeta()->setName('description', 'Albumy w hhbd.pl');
    $this->params = $this->getRequest()->getParams();
    $this->view->currentUrl = $this->getRequest()->getBaseUrl() . $this->getRequest()->getRequestUri();
  }

  public function indexAction()
  {
    $page = (!empty($this->params['page']))?$this->params['page']:1;
    
    $this->view->popularAlbums = Model_Album_Api::getInstance()->getPopular(10);
    $this->view->bestAlbums = Model_Album_Api::getInstance()->getBest(10);
    $this->view->albums = Model_Album_Api::getInstance()->getNewest(12, $page);
    $albumCount =  Model_Album_Api::getInstance()->getAlbumCount();

    // pagination
    if ($albumCount > 12) {
      $paginator = Zend_Paginator::factory($albumCount);
      $paginator->setCurrentPageNumber($page);
      $paginator->setItemCountPerPage(12);
      $paginator->setPageRange(15);
      Zend_View_Helper_PaginationControl::setDefaultViewPartial('common/_paginatorTable.phtml');
      $this->view->paginator = $paginator;
    }

    // seo
    $this->view->title = 'Lista polskich albumów hip-hopowych';
    $this->view->headTitle($this->view->title, 'PREPEND');    

    $keywords = array();
    $description = array();
    foreach ($this->view->albums->items as $key => $value) {
      $keywords[] = $value->artist->name;
      $description[] = $value->title;
    }

    $this->view->headMeta()->setName('keywords', 'lista albumów, ' . implode(array_unique($keywords), ', '));
    $this->view->headMeta()->setName('description', 'Lista wydanych w polsce albumów hip-hopowych, ' . implode(array_unique($description), ', '));
  }
    
  public function announcedAction()
  {
    $this->view->title = 'Albumy zapowiedziane';
    $this->view->headTitle($this->view->title, 'PREPEND');
    
    $page = (!empty($this->params['page']))?$this->params['page']:1;
    
    $this->view->popularAlbums = Model_Album_Api::getInstance()->getPopular(10);
    $this->view->bestAlbums = Model_Album_Api::getInstance()->getBest(10);
    
    $albumCount =  Model_Album_Api::getInstance()->getAnnouncedCount();
    $this->view->albums = Model_Album_Api::getInstance()->getAnnounced(12, $page);
    
    if ($albumCount > 12) {
      $paginator = Zend_Paginator::factory($albumCount);
      $paginator->setCurrentPageNumber($page);
      $paginator->setItemCountPerPage(12);
      $paginator->setPageRange(15);
      Zend_View_Helper_PaginationControl::setDefaultViewPartial('common/_paginatorTable.phtml');
      $this->view->paginator = $paginator;
    }
    
    $keywords = array();
    $description = array();
    foreach ($this->view->albums->items as $key => $value) {
      $keywords[] = $value->artist->name;
      $description[] = $value->title;
    }
    
    $this->view->headMeta()->setName('keywords', 'lista albumów, ' . implode(array_unique($keywords), ', '));
    $this->view->headMeta()->setName('description', 'Lista zapowiedzianych w polsce albumów hip-hopowych, ' . implode(array_unique($description), ', ') . '. Sprawdź najbliższe premiery!');
    
    $this->renderScript('album/index.phtml');
  }

  public function viewAction()
  {
    $params = $this->getRequest()->getParams();
    $album = Model_Album_Api::getInstance()->find($params['id'], true);
    $album->autoDescription = $this->_generateDescription($album);
    $this->view->album = $album;
    $this->view->artistsAlbums = Model_Album_Api::getInstance()->getArtistsAlbums($album->artist->id, array($album->id), 10);
    $this->view->popularAlbums = Model_Album_Api::getInstance()->getPopular(10);
    $this->view->bestAlbums = Model_Album_Api::getInstance()->getBest(10);
    if (!empty($album->label)) {
      $this->view->labelsAlbums = Model_Album_Api::getInstance()->getLabelsAlbums($album->label->id, array($album->id), 10);
    }
    
    $this->view->comments = Model_Comment_Api::getInstance()->getComments($album->id, Model_Comment_Container::TYPE_ALBUM);

    $this->view->title = $album->artist->name . ' - ' . $album->title . ' (' . $album->year . ')';
    $this->view->headTitle()->set($this->view->title, 'PREPEND');
    $this->view->headMeta()->setName('keywords', $album->artist->name . ',' . $album->title . ',teksty,premiera,download,tracklista,' . $album->label->name);
    
    $releaseDate = '';
    if (!empty($album->releaseDateNormalized)) {
      if ($album->isAnnounced()) {
        $releaseInfo = ', premiera albumu zaplanowana jest na ' . $album->releaseDateNormalized . ', przez wytwórnię ' . $album->label->name;
      }
      else
      {
        $releaseInfo = ', album został wydany ' . $album->releaseDateNormalized . ', przez wytwórnię ' . $album->label->name;
      }
    }
    else 
    {
      if ($album->isAnnounced()) {
        $releaseInfo = ', premiera albumu zaplanowana przez wytwórnię ' . $album->label->name;
      }
      else
      {
        $releaseInfo = ', album został wydany przez wytwórnię ' . $album->label->name;
      }
      
    }
    
    $this->view->headMeta()->setName('description', $album->artist->name . ' "' . $album->title . '"' . $releaseInfo . '. U nas teksty utworów, tracklista, oraz inne szczegółowe informacje o albumie.');
    
    // Open Graph Protocol (see more: http://mgp.me)
    $og = new Jkl_Og('Hhbd.pl');
    $og->setTitle($album->artist->name . ' - ' . $album->title . ' (' . $album->year . ')');
    $description = empty($album->description)?$album->autoDescription:$album->description;
    $og->setDescription($description);
    $og->setImage($album->thumbnail);
    $og->setType('album');
    $this->view->og = $og->getMetaData();
    
  }
  
  // description autogeneration, displayedfor SEO purposes
  private function _generateDescription($album)
  {
    $description = null;
    $music = array();
    $scratch = array();
    $feat = array();
    $rap = array();

    foreach ($album->tracklist->items as $key => $value) {
      foreach ($value->featuring->items as $data) {
        $feat[] = $data->name;
      }
      foreach ($value->music->items as $data) {
        $music[] = $data->name;
      }
      foreach ($value->scratch->items as $data) {
        $scratch[] = $data->name;
      }
      foreach ($value->artist->items as $data) {
        $rap[] = $data->name;
      } 
    }

    $eps = array();
    foreach ($album->eps->items as $key => $value) {
      $eps[] = $value->title;
    }

    if ($album->isAnnounced()) {
      $description = 'Długo oczekiwany album ' . $album->title . ', został zapowiedziany przez wytwórnię ' . $album->label->name .
      '. Premiera planowana jest na ' . $album->releaseDateNormalized . ', czyli już niedługo! ' .
      (!empty($album->tracklist->items)?'Album ma zawierać ' . sizeof($album->tracklist->items) . ' utworów. ' .
      'Płyta będzie otwarta utworem ' . $album->tracklist->items[0]->title . ', a zamknięta utworem ' . $album->tracklist->items[sizeof($album->tracklist->items)-1]->title . '. ':'') .
      'Czekamy z niecierpliwością. ' .
      '';
    } else {
      $description = 'Album "' . $album->title . '", został wydany przez wytwórnię ' . $album->label->name .
      ', premiera odbyła się ' . $album->releaseDateNormalized . '. ' .
      (!empty($album->tracklist->items)?'Album zawiera ' . sizeof($album->tracklist->items) . ' utworów' . (($album->duration!="--")?' i trwa ' . $album->duration:''). '. ' .
      'Płyta rozpoczyna się utworem "' . $album->tracklist->items[0]->title . '", a kończy utworem "' . $album->tracklist->items[sizeof($album->tracklist->items)-1]->title . '". ':'');
    }
    $description .= 
      ((!empty($eps))?'Album "' . $album->title . '" jest poprzedzony singlami: "' . implode(array_unique($eps), '", "') . '". ':'') .
      ((!empty($album->epFor))?'Album "' . $album->title . '" jest singlem do albumu "' . $album->epFor->title . '". ':'') .
      ((!empty($rap))?'Za rymy i rap na płycie, odpowiedzialni są: ' . implode(array_unique($rap), ', ') . '. ':'') . 
      ((!empty($music))?'Warstwę muzyczną zapewnili: ' . implode(array_unique($music), ', ') . '. ':'') . 
      ((!empty($scratch))?'Scratch i cuty na płycie to zasługa: ' . implode(array_unique($scratch), ', ') . '. ':'') . 
      ((!empty($feat))?'Gościnnie na albumie udzielają się: ' . implode(array_unique($feat), ', ') . '. ':'') . 
      '' . 
      $album->artist->name . ' to prawdziwy polski hip-hop. ' . 
      'Aby zobaczyć teksty piosenek, należy kliknąć w tytuły na liście powyżej. Do każdej piosenki dołączony jest teledysk. Jeżeli teledyski nieodpowiadają tytułowi, możesz to zgłosić.';
    return $description;
  }
}