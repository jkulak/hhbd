<?php

class IndexController extends Zend_Controller_Action
{
    public function init()
    {
        $this->view->headTitle()->headTitle('Hhbd.pl - Hip-hopowa baza danych', 'SET');
        $this->view->headMeta()->setName('description', 'Baza danych polskiego hip-hopu. U nas znajdziesz wszystkie interesujące informacje na temat albumów, premier, wykonawców i wytwórni.');
    }

    public function indexAction()
    {
        $this->view->news = Model_News_Api::getInstance()->getRecent(9);
        $this->view->newestList = Model_Album_Api::getInstance()->getNewest(7);
        // Warsaw's day, as the application's timezone has it, not the database's UTC (#197)
        $this->view->today = date('Y-m-d');
        $this->view->onThisDay = Model_Album_Api::getInstance()->getOnThisDay($this->view->today);
        $this->view->announcedList = Model_Album_Api::getInstance()->getAnnounced(5);

        $artist = Model_Artist_Api::getInstance()->find(6, true);
        $artist->addPopularSongs(Model_Song_Api::getInstance()->getMostPopularByArtist($artist->id, 4, true));
        $this->view->mainArtist = $artist;

        $this->view->popularSongs = Model_Song_Api::getInstance()->getMostPopular(10);
        // This week's, filled up from all time while the week has too few views (#194)
        $week = Model_PageView_Api::since('tydzien', date('Y-m-d'));
        list($this->view->popularArtists) = Model_Artist_Api::getInstance()->popularSince($week, 10);
        list($this->view->popularAlbums) = Model_Album_Api::getInstance()->popularSince($week, 5);
        $this->view->canonicalUrl = $this->site() . '/';
        $this->view->og = $this->og('Hhbd.pl - Hip-hopowa baza danych', $this->view->getHelper('StructuredData')::DESCRIPTION);
    }

    /** What hhbd.pl is and how much it holds, counted now, for people and for search (#218) */
    public function aboutAction()
    {
        $this->view->headTitle()->set('O nas - Hhbd.pl');
        $this->view->figures = $this->view->Figures();
        $this->view->headMeta()->setName('description', $this->view->figures);
        $this->view->canonicalUrl = $this->site() . $this->view->url(array(), 'about');
        $this->view->og = $this->og('O nas - Hhbd.pl', $this->view->figures);
    }

    public function contactAction()
    {
    }

    /** What the site measures and shows through Google, and how to change the choice (#162) */
    public function privacyAction()
    {
        $this->view->headTitle()->set('Prywatność - Hhbd.pl');
        $this->view->headMeta()->setName('description', 'Jakie dane zbiera Hhbd.pl, po co, jak długo je przechowuje i jak zmienić zgodę na ciasteczka.');
    }

    /** Open Graph for a page of the site itself, which has no picture of its own (#177) */
    private function og($title, $description)
    {
        $og = new Jkl_Og('Hhbd.pl', $this->site());
        $og->setTitle($title);
        $og->setDescription($description);
        $og->setUrl($this->view->canonicalUrl);
        $og->setType('website');
        return $og->getMetaData();
    }

    /** The site's origin as the request reached it, https:// behind the edge (#147) */
    private function site()
    {
        return $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost();
    }
}
