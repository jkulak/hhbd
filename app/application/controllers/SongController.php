<?php

class SongController extends Zend_Controller_Action
{
  
  public function init()
  {
    $this->view->headMeta()->setName('keywords', 'polski hip-hop, albumy');
    $this->view->headTitle()->headTitle('Piosenki', 'PREPEND');
    $this->view->headMeta()->setName('description', 'Piosenki, teksty, teledyski w hhbd.pl');
    $this->params = $this->getRequest()->getParams();
    
    //set the context switching
    $this->_helper->getHelper('contextSwitch')
      ->addActionContext('view', 'xml')
      ->addActionContext('edit-lyrics', 'xml')
        ->initContext();
  }

  public function indexAction()
  {
  }

  public function flagVideoAction()
  {
    $reg = "/^([\w\d\.:]+).*-s(\d+).*/";
    $id = preg_replace($reg, "$2", $_SERVER['HTTP_REFERER']);

    // save flag info
    $result = Model_Song_Api::getInstance()->flagVideo($id);
    exit();
  }
  
  public function viewAction()
  {
    // content
    $params = $this->getRequest()->getParams();
    $song = Model_Song_Api::getInstance()->find($params['id'], true);
    $song->autoDescription = $this->_generateDescription($song);
    $this->view->song = $song;
    
    $this->view->comments = Model_Comment_Api::getInstance()->getComments($song->id, Model_Comment_Container::TYPE_SONG);
    
    // sidenotes
    $albumSongs = array();
    foreach ($this->view->song->featured->items as $key => $value) {
      $albumSongs[] = Model_Song_Api::getInstance()->getTracklist($value->id, null);
    }
    $this->view->albumSongs = $albumSongs;
    $this->view->popularSongs = Model_Song_Api::getInstance()->getMostPopular(15);
    $this->view->autoPlay = isset($this->params['autoplay']);
    
    $this->view->editors = Model_User::getInstance()->getLyricsEditors($song->id);

    // seo meta
    $this->view->headTitle()->set($this->view->song->albumArtist->name . ' - ' . $this->view->song->title . ' (' . $this->view->song->featured->items[0]->title . ')');
        $this->view->headMeta()->setName('keywords', $this->view->song->albumArtist->name . ',' . $this->view->song->title . ',tekst,teledysk,sample');
    if (!empty($song->lyrics)) {
        $this->view->headMeta()->setName('description', 'Tekst i teledysk utworu ' . $this->view->song->albumArtist->name . ' - ' . $this->view->song->title . '. ' . Jkl_Tools_String::trim_str(str_replace(array(" <br />\r", "<br />\r ", "<br />\r"), ', ', $song->lyrics), 160, false));
    } else {
      $this->view->headMeta()->setName('description', 'Teledysk i informacje o utworze ' . $this->view->song->albumArtist->name . ' - ' . $this->view->song->title . '. Na razie nie mamy tekstu, ale jeżeli go posiadasz, możesz dodać.');
      
      Model_Song_Api::getInstance()->getArtists($params['id']);
    }

  }
  
  /**
   * Processes lyrics
   *
   * @return void
   * @since 2011-02-06
   * @author Kuba
   * @file: SongController.php
   **/
  public function processLyricsAction()
  {
    // check if user is logged in
    $loggedIn = Zend_Auth::getInstance()->hasIdentity();
    
    // check if it's a post request (trying to save data)
    if ($this->getRequest()->isPost()) {
      
      $lyrics = htmlentities($this->params['lyrics'], ENT_COMPAT, "UTF-8");
      $lyrics = nl2br($lyrics); // replace new lines to <br /> - only allowed html tag in database
      $songId = $this->params['id'];
      
      if ($loggedIn) {
        // zalogowany i post, więc zapisujemy tekst
        $userId = Zend_Auth::getInstance()->getIdentity()->usr_id;
        $result = $this->_saveLyrics($songId, $lyrics, $userId);
        if ($this->getRequest()->isXmlHttpRequest()) {
          // zalgowany i odpowiedź zwracamy jsonem
          if (($result === 0) or ($result == 1)) {
            $this->_helper->json(array('success' => true, 'result-message' => 'Yupi!', 'lyrics' => $lyrics));
          }
          else
          {
            $this->_helper->json(array('success' => false, 'result-message' => 'Przepraszam, ale wystąpił problem z zapisem, spróbuj ponownie za kilka minut.', 'lyrics' => $lyrics));
          }
        }
        else
        {
          $this->view->result = $result;
          $this->view->song = Model_Song_Api::getInstance()->find($songId);
        }
      }
      else
      {
        // post i niezalogowany
        if ($this->getRequest()->isXmlHttpRequest()) {
          // odpowiedź o tym, że nie zalogowany zwracamy jsonem
          $this->_helper->json(array('success' => false, 'result-message' => 'Musisz być zalogowany, żeby edytować tekst!', 'lyrics' => $lyrics, 'id' => $songId));
        }
        else
        {
          // zwracamy normalną opowiedź, że nie zalogowany
          $this->view->result = false;
        }
      }
    }
    else
    {
        if ($loggedIn) {
          // pokazujemy formularz, czyli nic nie robimy tutaj
          $song = Model_Song_Api::getInstance()->find($this->params['id']);
          $this->view->song = $song;
        }
        else
        {
          // get i niezalogowany
          // pokazujemy informacje, ze to tylko dla zalogowanych
          $this->_forward('not-logged-in', 'User');
        }
    }
  }

  /**
   * Save lyrics
   *
   * @return number of affected rows
   * @author Kuba
   **/
  private function _saveLyrics($songId, $lyrics, $userId)
  {
    $result = Model_Song_Api::getInstance()->saveLyrics($songId, $lyrics, $userId);
    
    /*
      TODO 2011-02-02 Post information to Twitter, that lyrics for the song, have beenupdated
    */
    return $result;
  }
  
  // description autogeneration, displayedfor SEO purposes
  private function _generateDescription($song)
  {
    $description = '';
    
    $description .= 'Utwór ' . $song->title . ', ';
    if (!empty($song->artist->items)) {
      $description .= 'na którym wokalnie udziela się ';
      foreach ($song->artist->items as $key => $value) {
        $description .= $value->name;
        if (sizeof($song->featured->items) - 1 > $key) {
          $description .= (sizeof($song->artist->items)-3 == $key)?', ':' i ';
        }
      }
      $description .= ', ';
    }
    
    $description .= 'został wydany na ' . sizeof($song->featured->items) . ' ';
    $description .= ((sizeof($song->featured->items)>1)?'albumach':'albumie') . ': ';
    foreach ($song->featured->items as $key => $value) {
      $description .= '"' . $value->title . '"';
      if (sizeof($song->featured->items) - 1 > $key) {
        $description .= (sizeof($song->featured->items) - 2 == $key)?', ':' i ';
      }
    }
    $description .= '. ';
    
    if (!empty($song->music->items)) {
      $description .= 'Muzykę do tego numeru zrobił ';
      foreach ($song->music->items as $key => $value) {
        $description .= $value->name;
        if (sizeof($song->featured->items) - 1 > $key) {
          $description .= (sizeof($song->music->items) - 1 == $key)?', ':' i ';
        }
      }
      $description .= '. ';
    }
    
    if (!empty($song->scratch->items)) {
      $description .= 'Skreczowaniem i cutami zajął się  ';
      foreach ($song->scratch->items as $key => $value) {
        $description .= $value->name;
        if (sizeof($song->featured->items) - 1 > $key) {
          $description .= (sizeof($song->scratch->items) - 2 == $key)?', ':' i ';
        }
      }
      $description .= '. ';
    }
    
    if (!empty($song->duration)) {
      $description .= 'Utwór ' . $song->title . ' trwa ' . $song->duration . '. ';
    }

    if (!empty($song->featuring->items)) {
      $description .= 'Gościnnie na albumie udzielaja się ';
      foreach ($song->featuring->items as $key => $value) {
        $description .= $value->name . ' (' . $value->featType . ')';
        if (sizeof($song->featuring->items) - 1 > $key) {
          $description .= (sizeof($song->featuring->items) - 2 != $key)?', ':' i ';
        }
      }
      $description .= '. ';
    }
    
    if (empty($song->lyrics)) {
      $description .= 'Nie posiadamy na razie tekstu tego utworu. Jeżeli masz, wyślij nam tekst ' . $song->title . '. ';
    }
    
    return $description;
  }

}