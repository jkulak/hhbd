<?php

/**
 * The addresses hhbd had before the .html ones (#26), still linked from old profiles, news and
 * other sites: each answers with a permanent redirect to the page it named. A slug that names
 * no row, or several, gets the search for its words instead, and not for good, as the row may
 * come with an import.
 */
class LegacyController extends Zend_Controller_Action
{
    /** /n/, /a/, /l/, /s/, /wykonawca/, /album/ and /wytwornia/, by the slug in urlname */
    public function slugAction()
    {
        $kind = $this->getRequest()->getParam('kind');
        $slug = (string) $this->getRequest()->getParam('slug');
        $legacy = Model_Legacy_Api::getInstance();

        $ids = $legacy->idsBySlug($kind, $slug);
        $url = 1 === count($ids) ? $legacy->urlOf($kind, $ids[0]) : null;
        if (null !== $url) {
            $this->_helper->redirector->gotoUrl($url, array('code' => 301));
            return;
        }
        $this->_helper->redirector->gotoUrl(Model_Legacy_Api::searchUrl($slug), array('code' => 302));
    }

    /** /news/1877, by the news item's id */
    public function newsAction()
    {
        $url = Model_Legacy_Api::getInstance()->newsUrl($this->getRequest()->getParam('id'));
        if (null === $url) {
            throw new Zend_Controller_Action_Exception('No such news item', 404);
        }
        $this->_helper->redirector->gotoUrl($url, array('code' => 301));
    }
}
