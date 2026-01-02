<?php

class SearchController extends Zend_Controller_Action
{

  public function init()
  {
    $this->view->headMeta()->setName('keywords', 'polski hip-hop, albumy');
    $this->view->headTitle()->headTitle('Wyniki wyszukiwania', 'PREPEND');
    $this->view->headMeta()->setName('description', 'Wyniki wyszukiwania www.hhbd.pl');
    $this->params = $this->getRequest()->getParams();
  }

  public function indexAction()
  {
    $searchQuery = htmlentities((!empty($this->params['q']))?$this->params['q']:'niczego?', ENT_COMPAT, "UTF-8");
    $type = (!empty($this->params['tp']))?$this->params['tp']:null;

    if (isset($type)) {
      if (!in_array($type, array('album', 'wykonawca', 'utwor', 'wytwornia'))) {
        $type = null;
      }
    }

    $page = (!empty($this->params['page']))?$this->params['page']:1;
    $limit = (!empty($type)?12:4);

    // search artists (names and nicknames)
    if (!isset($type) or $type=='wykonawca') {
      $artists = Model_Artist_Api::getInstance()->getLike($searchQuery, $limit, $page);
      $nicknames = Model_Artist_Api::getInstance()->getNicknamesLike($searchQuery, $limit, $page);
      $resultArtists = new Jkl_List();
      $resultArtists->items = array_slice(array_unique(array_merge($artists->items, $nicknames->items)), 0, $limit);
      $this->view->resultArtists = $resultArtists;
    }

    // search album titles
    if (!isset($type) or $type=='album') {
      $resultAlbums = Model_Album_Api::getInstance()->getLike($searchQuery, $limit, $page);
      $this->view->resultAlbums = $resultAlbums;
    }

    // search song names
    if (!isset($type) or $type=='utwor') {
      $limit = (!empty($type)?24:4);
      $resultSongs = Model_Song_Api::getInstance()->getLike($searchQuery, $limit, $page);
      $this->view->resultSongs = $resultSongs;
    }

    // search label names
    if (!isset($type) or $type=='wytwornia') {
      $resultLabels = Model_Label_Api::getInstance()->getLike($searchQuery, $limit, $page);
      $this->view->resultLabels = $resultLabels;
    }

    $totalArtistCount = Model_Artist_Api::getInstance()->getLikeCount($searchQuery);
    $totalAlbumCount = Model_Album_Api::getInstance()->getLikeCount($searchQuery);
    $totalSongCount = Model_Song_Api::getInstance()->getLikeCount($searchQuery);
    $totalLabelCount = Model_Label_Api::getInstance()->getLikeCount($searchQuery);

    // need to bulid paginator per each type
    if (isset($type)) {
      switch ($type) {
        case 'wykonawca':
            $totalCount = $totalArtistCount;
            $itemsPerPage = 12;
            $totalResults = sizeof($resultArtists->items);
          break;
        case 'album':
            $totalCount = $totalAlbumCount;
            $itemsPerPage = 12;
            $totalResults = sizeof($resultAlbums->items);
          break;
        case 'utwor':
            $totalCount = $totalSongCount;
            $itemsPerPage = 24;
            $totalResults = sizeof($resultSongs->items);
          break;
        case 'wytwornia':
            $totalCount = $totalLabelCount;
            $itemsPerPage = 12;
            $totalResults = sizeof($resultLabels->items);
          break;
        default:
          $itemsPerPage = 12;
          break;
      }
      // we have detailed search
      if ($totalCount > $itemsPerPage) {
        $paginator = Zend_Paginator::factory($totalCount);
        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage($itemsPerPage);
        $paginator->setPageRange(15);
        $paginator->type = $type;
        $paginator->searchQuery = $searchQuery;
        Zend_View_Helper_PaginationControl::setDefaultViewPartial('search/_paginator.phtml');
        $this->view->paginator = $paginator;
        // print_r($paginator);
      }
      $this->view->totalCount = $totalCount;
      $this->view->totalResults = $totalResults;
    }
    else
    {
      $this->view->totalCount = sizeof($resultArtists->items) + sizeof($resultAlbums->items) + sizeof($resultSongs->items) + sizeof($resultLabels->items);
    }

    // search lyrics ???
    $this->view->type = $type;
    $this->view->searchQuery = $searchQuery;

    $this->view->totalArtistCount = $totalArtistCount;
    $this->view->totalAlbumCount = $totalAlbumCount;
    $this->view->totalSongCount = $totalSongCount;
    $this->view->totalLabelCount = $totalLabelCount;

    $this->view->recentSearches = Model_Search_Api::getInstance()->getRecent();
    $this->view->mostPopularSearches = Model_Search_Api::getInstance()->getMostPopular();

    if ($this->view->totalCount > 0) {
      Model_Search_Api::getInstance()->saveSearch($searchQuery);
    }

    $this->view->headMeta()->setName('keywords', $searchQuery . ',wyniki,wyszukiwania,polski hip-hop,albumy,wykonawcy,wytwórnie,utwory,teksty,teledyski');
    $this->view->headTitle()->headTitle('Wyniki wyszukiwania ' .  $searchQuery . ' na największej stronie o polskim hip-hopie!', 'PREPEND');
    $this->view->headMeta()->setName('description', 'Wyniki wyszukiwania "' .  $searchQuery . '" w www.hhbd.pl');
  }
}
