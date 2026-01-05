<?php

/**
 * ProfileLink helper
 *
 * Renders an anchor to the currently logged-in user's profile.
 */
class Zend_View_Helper_ProfileLink extends Zend_View_Helper_Abstract
{
    /**
     * Returns HTML link to the logged-in user's profile or empty string when not logged in.
     *
     * @return string
     */
    public function ProfileLink()
    {
        $auth = Zend_Auth::getInstance();
        if (! $auth->hasIdentity()) {
            return '';
        }

        $identity = $auth->getIdentity();
        $seo = Jkl_Tools_Url::createUrl($identity->usr_display_name);
        $url = $this->view->url(
            array('id' => $identity->usr_id, 'seo' => $seo),
            'user',
            true
        );

        return '<a href="' . $this->view->escape($url) . '">' . $this->view->escape($identity->usr_display_name) . '</a>';
    }
}
