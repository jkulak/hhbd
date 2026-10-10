<?php

/**
 * News Container
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 23 Decemnber, 2010
 * @package default
 **/

#[\AllowDynamicProperties]
class Model_News_Container
{
    public $title = null;
    public $content = null;
    public $attachment = null;
    public $addedBy = null;
    public $added = null;
    public $updatedBy = null;
    public $updated;

    public function __construct($params, $full = false)
    {
        $configApp = Zend_Registry::get('Config_App');

        $this->id = $params['nws_id'];
        $this->title = $params['nws_title'];
        // A list shows an excerpt: plain text cut after a word, escaped, as the views print it (#163)
        $this->content = ($full) ? $params['nws_content'] : htmlspecialchars(Jkl_Tools_String::excerpt($params['nws_content'], 200), ENT_QUOTES, 'UTF-8');
        if (!empty($params['nws_attachment_url'])) {
            // The file name encoded: old names hold spaces, Polish letters and a literal % (#133)
            $this->attachment = new Model_Image_Container(array('url' => $configApp['paths']['newsImagePath'] . rawurlencode($params['nws_attachment_url'])));
        }
        $this->added = $params['nws_added'];
        $this->addedNormalized = Jkl_Tools_Date::getNormalDate($params['nws_added']);
        $this->url = Jkl_Tools_Url::createUrl($this->title);
    }

    /**
     * Get full URL path for news article
     *
     * @return string Full URL path (e.g., /news-title-n123.html)
     */
    public function getUrl()
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        return $router->assemble(
            array('id' => $this->id, 'seo' => $this->url),
            'news',
            true
        );
    }
}
