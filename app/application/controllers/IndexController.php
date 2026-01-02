<?php

class IndexController extends Zend_Controller_Action {
    public function init() {
        $this->view->headTitle()->headTitle('Hhbd.pl - Hip-hopowa baza danych', 'SET');
        $this->view->headMeta()->setName('description', 'Baza danych polskiego hip-hopu. U nas znajdziesz wszystkie interesujące informacje na temat albumów, premier, wykonawców i wytwórni.');
    }

    public function indexAction() {
        $this->view->news = Model_News_Api::getInstance()->getRecent(9);
        $this->view->newestList = Model_Album_Api::getInstance()->getNewest(7);
        $this->view->announcedList = Model_Album_Api::getInstance()->getAnnounced(5);

        $artist = Model_Artist_Api::getInstance()->find(6, true);
        $artist->addPopularSongs(Model_Song_Api::getInstance()->getMostPopularByArtist($artist->id, 4, true));
        $this->view->mainArtist = $artist;

        $this->view->popularSongs = Model_Song_Api::getInstance()->getMostPopular(10);
        $this->view->popularArtists = Model_Artist_Api::getInstance()->getMostPopular(10);
        $this->view->popularAlbums = Model_Album_Api::getInstance()->getPopular(5);
    }

    public function aboutAction() {
    }

    public function contactAction() {
    }
}
