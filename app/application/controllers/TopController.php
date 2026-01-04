<?php

class TopController extends Zend_Controller_Action
{
    public function init()
    {
        $this->view->headMeta()->setName('keywords', 'top 10,ranking,polski hip-hop,najlepsze albumy,najpopularniejsi artyści');
        $this->view->headTitle()->headTitle('Top 10 - Hhbd.pl', 'PREPEND');
        $this->view->headMeta()->setName('description', 'Top 10 - rankingi polskiego hip-hopu. Najpopularniejsze albumy, artyści, utwory i wytwórnie.');
    }

    public function indexAction()
    {
        $this->view->headTitle()->set('Top 10 - Rankingi polskiego hip-hopu - Hhbd.pl');

        $this->view->popularAlbums = Model_Album_Api::getInstance()->getPopular(10);
        $this->view->bestAlbums = Model_Album_Api::getInstance()->getBest(10);
        $this->view->popularSongs = Model_Song_Api::getInstance()->getMostPopular(10);
        $this->view->popularArtists = Model_Artist_Api::getInstance()->getMostPopular(10);
        $this->view->labelsWithMostAlbums = Model_Label_Api::getInstance()->getWithMostAlbums(10);
        $this->view->popularSearches = Model_Search_Api::getInstance()->getMostPopular(10);
        $this->view->artistsWithMostSoloAlbums = Model_Artist_Api::getInstance()->getWithMostSoloAlbums(10);
        $this->view->artistsWithMostProjects = Model_Artist_Api::getInstance()->getWithMostProjectAlbums(10);
    }
}
