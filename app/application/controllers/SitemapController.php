<?php

/**
 * The sitemaps robots.txt names (#147): an index of the five, and one per kind of page. Each
 * address is absolute, as the protocol requires, and the canonical one its page names in
 * rel="canonical". No lastmod: the rows' updated says when a row changed, not when its page
 * did (a new album changes its artist's page), and a date that is not true is worse than none.
 */
#[\AllowDynamicProperties]
class SitemapController extends Zend_Controller_Action
{
    /** The kinds of page with a sitemap, in the index's order */
    public const KINDS = array('pages', 'albums', 'artists', 'news', 'songs', 'labels');

    /** The pages that list others: the lists, the years (#196) and the rankings over each period (#194) */
    public const PAGES = array('/', '/albumy.html', '/wykonawcy.html', '/wytwornie.html', '/premiery.html', '/top10.html');

    /** The protocol's most for one file; hhbd has about 12,000 songs, the longest list */
    public const LIMIT = 50000;

    public function init()
    {
        $this->params = $this->getRequest()->getParams();
    }

    /** XML without the site's layout; after the action, so a 404 keeps the site's page */
    public function postDispatch()
    {
        $this->_helper->layout->setLayout('sitemap');
        $this->getResponse()->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
    }

    public function indexAction()
    {
        $site = $this->site();
        $this->view->sitemaps = array_map(function ($kind) use ($site) {
            return $site . '/sitemap-' . $kind . '.xml';
        }, self::KINDS);
    }

    public function viewAction()
    {
        switch ($this->params['type']) {
            case 'pages':
                $this->view->urls = $this->pages();
                return;
            case 'albums':
                // One entry per album, however many artists it credits (#58).
                $list = Model_Album_Api::getInstance()->getAlbumsSitemap(self::LIMIT);
                break;
            case 'news':
                $list = Model_News_Api::getInstance()->getRecent(self::LIMIT, false);
                break;
            case 'artists':
                $list = Model_Artist_Api::getInstance()->getRecentlyAdded(self::LIMIT);
                break;
            case 'songs':
                $list = Model_Song_Api::getInstance()->getSitemap(self::LIMIT);
                break;
            case 'labels':
                $list = Model_Label_Api::getInstance()->getRecent(self::LIMIT);
                break;
            default:
                throw new Zend_Controller_Action_Exception('No sitemap ' . $this->params['type'], 404);
        }
        $site = $this->site();
        $urls = array();
        foreach ($list->items as $item) {
            $urls[] = $site . $item->getUrl();
        }
        $this->view->urls = $urls;
    }

    /** The lists and the rankings, at their absolute addresses */
    private function pages()
    {
        $paths = self::PAGES;
        // Each year's albums, and the list of the years (#196)
        $paths[] = '/albumy/lata.html';
        foreach (Model_Album_Api::getInstance()->getYears() as $row) {
            $paths[] = '/albumy-' . $row['year'] . '.html';
        }
        foreach (array('/albumy/popularne', '/wykonawcy/popularni') as $base) {
            foreach (array_keys(Model_PageView_Api::PERIODS) as $period) {
                $paths[] = Model_PageView_Api::rankingUrl($base, $period);
            }
        }
        $site = $this->site();
        return array_map(function ($path) use ($site) {
            return $site . $path;
        }, $paths);
    }

    /** The site's origin as the request reached it, https:// behind the edge (#147) */
    private function site()
    {
        return $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost();
    }
}
