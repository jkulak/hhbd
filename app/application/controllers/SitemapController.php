<?php

#[\AllowDynamicProperties]
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
        // Absolute, as the sitemap protocol requires, and each entity's own canonical path, the
        // one its page names in rel="canonical".
        $site = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost();
        switch ($this->params['type']) {
            case 'albums':
                // One entry per album, however many artists it credits (#58).
                $list = Model_Album_Api::getInstance()->getAlbumsSitemap();
                break;
            case 'news':
                $list = Model_News_Api::getInstance()->getRecent(10000, false);
                break;
            case 'artists':
                $list = Model_Artist_Api::getInstance()->getRecentlyAdded(10000);
                break;
            case 'songs':
                $list = Model_Song_Api::getInstance()->getRecent(10000);
                break;
            case 'labels':
                $list = Model_Label_Api::getInstance()->getRecent(10000);
                break;
            default:
                $list = new Jkl_List();
                break;
        }
        $urls = array();
        foreach ($list->items as $item) {
            $urls[] = $site . $item->getUrl();
        }
        $this->view->urls = $urls;
    }
}
