<?php

class ArtistController extends Zend_Controller_Action
{

  public function init()
  {
    $this->view->headMeta()->setName('keywords', 'hhbd.pl, polski hip-hop, albumy');
    $this->view->headMeta()->setName('description', 'Albumy w hhbd.pl');

    $this->params = $this->getRequest()->getParams();
  }

  public function indexAction()
  {
    $this->view->firstLetters = Model_Artist_Api::getInstance()->getFirstLetters();

    $page = (!empty($this->params['letter']))?$this->params['letter']:'mostPopular';

    if ($page == 'mostPopular') {
      $this->view->artists = Model_Artist_Api::getInstance()->getMostPopular();
    }
    else {
      $this->view->artists = Model_Artist_Api::getInstance()->getLikeLetter($page, null);
    }
    
    $this->view->mostProjectAlbums = Model_Artist_Api::getInstance()->getWithMostProjectAlbums();
    $this->view->mostSoloAlbums = Model_Artist_Api::getInstance()->getWithMostSoloAlbums();
    $this->view->recentlyAdded = Model_Artist_Api::getInstance()->getRecentlyAdded();
    
    $this->view->current = $page;
    

    $this->view->title = 'Lista wykonawców';
    $this->view->headTitle()->headTitle('Lista polskich wykonawców hip-hop', 'PREPEND');
    $this->view->headMeta()->setName('description', 'Lista polskich wykonawców hip-hop');
    $this->view->headMeta()->setName('keywords', 'polski hip-hop,wykonawcy');    
  }

  // View artist detail page
  public function viewAction()
  {
    $artist = Model_Artist_Api::getInstance()->find($this->params['id'], true);
    $artist->addAlbums(Model_Album_Api::getInstance()->getArtistsAlbums($artist->id, array(), false, 'year'));
    
    if (!empty($artist->projects->items)) {
      $projectAlbums = new Jkl_List('Projects list');
      $temp = new Jkl_List('Temp');
      foreach ($artist->projects->items as $key => $value) {
        $temp = Model_Album_Api::getInstance()->getArtistsAlbums($value->id, array(), false, 'year');
        $projectAlbums->items = array_merge($temp->items, $projectAlbums->items);
      }
      $artist->addProjectAlbums($projectAlbums);
    }

    $artist->addFeaturing(Model_Album_Api::getInstance()->getFeaturingByArtist($artist->id, null));
    $artist->addMusic(Model_Album_Api::getInstance()->getMusicByArtist($artist->id, null));
    $artist->addScratch(Model_Album_Api::getInstance()->getScratchByArtist($artist->id, null));
    $artist->addPopularSongs(Model_Song_Api::getInstance()->getMostPopularByArtist($artist->id, 30));

    $artist->autoDescription = $this->generateDescription($artist);
    
    $this->view->artist = $artist;
    
    $this->view->comments = Model_Comment_Api::getInstance()->getComments($artist->id, Model_Comment_Container::TYPE_ARTIST);
    
    // seo
    $albumListTmp = array();
    foreach ($artist->albums->items as $key => $value) {
      $albumListTmp[] = $value->title;
    }
    if (!empty($artist->projectAlbums)) {
      foreach ($artist->projectAlbums->items as $key => $value) {
        $albumListTmp[] = $value->title;
      }
    }
    
    $albumList = array();
    for ($i=0; $i < 3; $i++) { 
      if (isset($albumListTmp[$i])) {
        $albumList[] = $albumListTmp[$i];
      }
    }
    
    // Open Graph Protocol (see more: http://mgp.me)
    $og = new Jkl_Og('Hhbd.pl');
    $og->setTitle($artist->name);
    $description = empty($artist->profile)?$artist->autoDescription:$artist->profile;
    $og->setDescription($description);
    $og->setImage($artist->photos->items[0]->url);
    $og->setType('musician');
    $this->view->og = $og->getMetaData();
    
    $this->view->headTitle()->headTitle($artist->name, 'PREPEND');
    // $this->view->headMeta()->setName('description', $artist->name . ' - teksty, dyskografia, biografia '. implode($albumList, ', '));
    if (!empty($artist->description)) {
      $this->view->headMeta()->setName('description', Jkl_Tools_String::trim_str($artist->description, 160));
    } else {
      $this->view->headMeta()->setName('description', Jkl_Tools_String::trim_str($artist->autoDescription, 160));
    }
    
    $this->view->headMeta()->setName('keywords', $artist->name . ',' . implode(',', $albumListTmp) . ',teksty,dyskografia,biografia' );
  }
  
  // description autogeneration, displayed for SEO purposes
  public function generateDescription($artist)
  {
    $description = '';
    
    if ((!empty($artist->alsoKnownAs->items)) OR (!empty($artist->realName))) {
      if ($artist->isBand()) {
          $description .= "Projekt ";
        }
        else {
          $description .= "Wykonawca ";
        }
    
      $description .= $artist->name;
    
      if (!empty($artist->alsoKnownAs->items)) {
        $description .= " znany jest także jako " . implode($artist->alsoKnownAs->items, ', ') . '. ';
      }
    
      if (!empty($artist->realName)) {
        $description .= "Naprawdę nazywa się " . $artist->realName . '. ';
      }
    }
    
    if (!empty($artist->since)) {
      if ($artist->isBand()) {
          $description .= "Skład został założony w ";
        }
        else {
          $description .= "Urodził się w ";
        }
        $description .= $artist->since . '. ';
      }
    
    if ($artist->cities->items) {
      if ($artist->isBand()) {
          $description .= "Miasto założenia ";
        }
        else {
          $description .= "Urodził się w ";
        }
        $description .= $artist->cities->items[0]['city'] . '. ';
    }
    
    if (!empty($artist->members->items)) {
      $description .= "W skład projektu wchodzą: ";
      foreach ($artist->members->items as $key => $value) {
        $description .= $value->name;
        if (sizeof($artist->members->items) - 1 > $key) {
          $description .= ', ';
        }
      }
      $description .= '. ';
    }

    if (!empty($artist->projects->items)) {
      $description .= $artist->name . " współtworzy następujące projekty ";
      foreach ($artist->projects->items as $key => $value) {
        $description .= $value->name;
        if (sizeof($artist->projects->items) - 1 > $key) {
          $description .= ', ';
        }
      }
      $description .= '. ';
    }
    
    if (!empty($artist->albums->items)) {
      $description .= $artist->name . " wydał " . sizeof($artist->albums->items) . ' ';
      $description .= (sizeof($artist->albums->items)==1)?'album':((sizeof($artist->albums->items)>4)?'albumów':'albumy');
      $description .= " w tym, między innymi ";
      foreach ($artist->albums->items as $key => $value) {
         $description .= '"' . $value->title . '"';
          if (sizeof($artist->albums->items) - 1 > $key) {
            $description .= ', ';
          }
      }
      $description .= '. Data wydania pierwszego albumu, to ';
      $description .= $artist->albums->items[sizeof($artist->albums->items)-1]->releaseDateNormalized . '. ';
      if (sizeof($artist->albums->items)>1) {
        $description .= "Natomiast ostatni ukazał się " . $artist->albums->items[0]->releaseDateNormalized . '. ';
      }
    }
    
    if (!empty($artist->projectAlbums->items)) {
      $description .= $artist->name . ' w projektach wydał ' . sizeof($artist->projectAlbums->items) . ' ';
      $description .= (sizeof($artist->projectAlbums->items)==1)?'album':((sizeof($artist->projectAlbums->items)>4)?'albumów, w tym między innymi':'albumy, w tym między innymi');
      $description .= ' ';
      foreach ($artist->projectAlbums->items as $key => $value) {
        $description .= '"' . $value->title . '"';
        if (sizeof($artist->projectAlbums->items) - 1 > $key) {
          $description .= ', ';
        }
      }
      $description .= '. ';
    }
    
    if ($artist->concertInfo) {
      $description .= 'Jeżeli chcesz zabukować występ ' . $artist->name . ' skorzystaj z danych kontaktowych na górze strony, obok zdjęcia wykonawcy. ';
    }
    
    if (!empty($artist->featuring->items)) {
      $description .= $artist->name . ' wystąpił gościnnie na ' . ((sizeof($artist->featuring->items)>1)?'albumach':'albumie');
      $description .= ' ';
      foreach ($artist->featuring->items as $key => $value) {
        $description .= '"' . $value->title . '"';
        if (sizeof($artist->featuring->items) - 1 > $key) {
          $description .= ', ';
        }
      }
      $description .= '. ';
    }
    
    if (!empty($artist->music->items)) {
      $description .= $artist->name . ' odpowiada za muzykę na ' . ((sizeof($artist->music->items)>1)?'albumach':'albumie');
      $description .= ' ';
      foreach ($artist->music->items as $key => $value) {
        $description .= '"' . $value->title . '"';
        if (sizeof($artist->music->items) - 1 > $key) {
          $description .= ', ';
        }
      }
      $description .= '. ';
    }

    if (!empty($artist->scratch->items)) {
      $description .= $artist->name . ' robił skrecze i cuty na ' . ((sizeof($artist->scratch->items)>1)?'albumach':'albumie');
      $description .= ' ';
      foreach ($artist->scratch->items as $key => $value) {
        $description .= '"' . $value->title . '"';
        if (sizeof($artist->scratch->items) - 1 > $key) {
          $description .= ', ';
        }
      }
      $description .= '. ';
    }
   
    if ($artist->trivia) {
      $description .= 'Jako ciekawostka: ' . $artist->trivia . '. ';
    }
    
    if (!empty($artist->popularSongs->items)) {
      $description .= 'Najpopularniejsze utwory '. $artist->name . ' to ';
      foreach ($artist->popularSongs->items as $key => $value) {
        $description .= '"' . $value->title . '"';
        if (sizeof($artist->popularSongs->items) - 1 > $key) {
          $description .= ', ';
        }
      }
      $description .= '. ';
    }

    if (!empty($artist->website)) {
      $description .= 'Więcej informacji o ' . $artist->name . ' możesz przeczytać na oficjalnej stronie www, pod adresem: ';
      $description .= $artist->website . '. ';
    }
    
    $description .= 'Żeby zobaczyć teskty piosenek, wybierz album, a następnie konkretną piosenkę. ';

    return $description;
  }
}