<?php

/**
 * The sitemaps robots.txt names (#147): an index of the five, and one per kind of page. Each
 * address is absolute, as the protocol requires, and the canonical one its page names in
 * rel="canonical". No lastmod: the rows' updated says when a row changed, not when its page
 * did (a new album changes its artist's page), and a date that is not true is worse than none.
 * Model_Sitemap_Api says which pages each lists.
 */
#[\AllowDynamicProperties]
class SitemapController extends Zend_Controller_Action
{
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
        }, Model_Sitemap_Api::KINDS);
    }

    public function viewAction()
    {
        $paths = Model_Sitemap_Api::getInstance()->paths($this->params['type']);
        if (null === $paths) {
            throw new Zend_Controller_Action_Exception('No sitemap ' . $this->params['type'], 404);
        }
        $site = $this->site();
        $this->view->urls = array_map(function ($path) use ($site) {
            return $site . $path;
        }, $paths);
    }

    /** The site's origin as the request reached it, https:// behind the edge (#147) */
    private function site()
    {
        return $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost();
    }
}
