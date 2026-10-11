<?php

/**
 * Open Graph tags, what Facebook, Messenger, X, Slack and the chats read to preview a link (#177):
 * a title, a description, the page's canonical address, a picture and Open Graph's own type
 * (music.album, music.song, profile, website, article). Every address is absolute, as Open
 * Graph wants; a path is made one on the origin the page was asked on, and a stand-in drawing
 * (no cover, no photo) is no picture at all.
 */
class Jkl_Og
{
    private $_title;
    private $_description;
    private $_image;
    private $_url;
    private $_siteName;
    private $_type;
    private $_origin;

    public const HHBD_FACEBOOK_APP_ID = "189696961054439";

    /**
     * @param string      $siteName
     * @param string|null $origin   scheme and host to put before a path, https://hhbd.pl; by
     *                              default the request's, as the edge passes it on (#147)
     */
    public function __construct($siteName, $origin = null)
    {
        $this->_siteName = $siteName;
        if (null === $origin) {
            $request = Zend_Controller_Front::getInstance()->getRequest();
            $origin = null !== $request ? $request->getScheme() . '://' . $request->getHttpHost() : '';
        }
        $this->_origin = rtrim((string) $origin, '/');
    }

    public function setTitle($title)
    {
        $this->_title = $title;
    }

    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /** A cover's, a photo's or a logo's path or address; a stand-in is left out */
    public function setImage($image)
    {
        $this->_image = $image;
    }

    /** The page's canonical address */
    public function setUrl($url)
    {
        $this->_url = $url;
    }

    public function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * Whether a path is a picture of the thing itself rather than the drawing that stands in
     * for a missing one: the placeholders, an album's cd.png and an artist's no.png
     */
    public static function isPicture($path)
    {
        return !empty($path)
            && false === strpos($path, '/img/placeholder/')
            && !in_array(basename((string) $path), array('cd.png', 'no.png'), true);
    }

    public function getMetaData()
    {
        $tags = array();
        if (isset($this->_title)) {
            $tags['og:title'] = $this->_title;
        }
        if (isset($this->_description)) {
            $tags['og:description'] = Jkl_Tools_String::metaDescription($this->_description, 297);
        }
        if (isset($this->_url)) {
            $tags['og:url'] = $this->absolute($this->_url);
        }
        if (self::isPicture($this->_image)) {
            $tags['og:image'] = $this->absolute($this->_image);
        }
        if (isset($this->_type)) {
            $tags['og:type'] = $this->_type;
        }
        $tags['og:site_name'] = $this->_siteName;
        $tags['og:locale'] = 'pl_PL';
        $tags['fb:app_id'] = self::HHBD_FACEBOOK_APP_ID;

        $ogMeta = '';
        foreach ($tags as $property => $content) {
            $ogMeta .= '<meta property="' . $property . '" content="' . htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
        }
        return $ogMeta;
    }

    /** An address as it is, a path on the origin */
    private function absolute($path)
    {
        return 1 === preg_match('#^https?://#i', (string) $path) ? $path : $this->_origin . '/' . ltrim((string) $path, '/');
    }
}
