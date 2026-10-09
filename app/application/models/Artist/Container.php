<?php

/**
 * Artist
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 11 October, 2010
 * @package default
 **/

#[\AllowDynamicProperties]
class Model_Artist_Container
{
    public const TYPE_ARTIST = "Wykonawca";
    public const TYPE_PROJECT = "Projekt";
    public const TYPE_MALE = "Raper";
    public const TYPE_FEMALE = "Raperka";

    private $_artistTypes = array(
      'x' => Model_Artist_Container::TYPE_ARTIST,
      'b' => Model_Artist_Container::TYPE_PROJECT,
      'm' => Model_Artist_Container::TYPE_MALE,
      'f' => Model_Artist_Container::TYPE_FEMALE);

    public $id;
    public $name;
    /** What tells the artist from another of the same name, '' for none (#102) */
    public $disambiguation = '';
    /** The name with its qualifier, "Solar (SBM Label)": the artist's own page and URL use it */
    public $qualifiedName;
    /** The name a list shows: the qualified one only when the list holds a namesake */
    public $displayName;

    public function __construct($params, $full = false)
    {
        $this->id = $params['art_id'];
        $this->name = $params['name'];
        if (!empty($params['disambiguation'])) {
            $this->disambiguation = $params['disambiguation'];
        }
        $this->qualifiedName = self::qualifiedNameOf($this->name, $this->disambiguation);
        $this->displayName = $this->name;
        $this->url = Jkl_Tools_Url::createUrl($this->qualifiedName);
        if (!empty($params['since'])) {
            $this->started = ($params['since'] != '0000-00-00') ? $params['since'] : null;
        }
        if (!empty($params['till'])) {
            $this->ended = ($params['till'] != '0000-00-00') ? $params['till'] : null;
        }

        if (!empty($params['albumCount'])) {
            $this->albumCount = $params['albumCount'];
        }

        // also known as
        if (!empty($params['aka'])) {
            $this->alsoKnownAs = $params['aka'];
        }

        // views count
        if (!empty($params['viewed'])) {
            $this->views = $params['viewed'];
        }

        // main photo
        if (!empty($params['photo'])) {
            $this->photo = $params['photo'];
        }

        // comment count
        if (isset($params['comment_count'])) {
            $this->commentCount = $params['comment_count'];
        }

        if ($full) {
            $this->realName = $params['realname'];
            $this->description = $params['profile'];

            //it happens it has only spaces, so it's trimmed
            $this->concertInfo = trim((string) $params['concertinfo']);

            $this->type = $this->_artistTypes[$params['type']];
            $this->isSpecial = ($params['special'] == 1) ? true : false;

            $this->trivia = trim($params['trivia']);

            if (!empty($params['website'])) {
                $website = $params['website'];
                if (substr_count($website, 'http://') == 0) {
                    $website = 'http://' . $website;
                }
                $this->website = $website;
            }

            $this->added = $params['added'];
            $this->addedBy = $params['addedby'];
            $this->updated = $params['updated'];
            $this->updatedBy = $params['updatedby'];

            $this->viewed = $params['viewed'];
            $this->status = $params['status'];
            $this->hits = $params['hits'];

            // list of photos
            $this->photos = $params['photos'];

            // band members
            if (!empty($params['members'])) {
                $this->members = $params['members'];
            }

            // member of bands
            if (!empty($params['projects'])) {
                $this->projects = $params['projects'];
            }

            // city
            $this->cities = $params['cities'];

            if (!empty($params['albums'])) {
                $this->albums = $params['albums'];
            }

            if (!empty($params['projectalbums'])) {
                $this->projectAlbums = $params['projectalbums'];
            }
        }
    }

    public function addAlbums($albums)
    {
        $this->albums = $albums;
    }

    public function addProjectAlbums($albums)
    {
        $this->projectAlbums = $albums;
    }

    public function addFeaturing($albums)
    {
        $this->featuring = $albums;
    }

    public function addMusic($albums)
    {
        $this->music = $albums;
    }

    public function addScratch($albums)
    {
        $this->scratch = $albums;
    }

    public function addPopularSongs($list)
    {
        $this->popularSongs = $list;
    }

    public function isBand()
    {
        return !empty($this->members->items);
    }

    public function __toString()
    {
        return $this->name;
    }

    /**
     * "Solar (SBM Label)" for a name with a qualifier, the name alone without one.
     *
     * @return string
     */
    public static function qualifiedNameOf($name, $disambiguation)
    {
        return '' === (string) $disambiguation ? (string) $name : $name . ' (' . $disambiguation . ')';
    }

    /**
     * Gives the artists of a list who share a name with another one in it their qualified name
     * to show; the rest keep the plain name, so a list with one Solar says "Solar" (#102).
     *
     * @param Jkl_List|array $artists
     * @return Jkl_List|array the same list
     */
    public static function qualifyNamesakes($artists)
    {
        $items = $artists instanceof Jkl_List ? $artists->items : $artists;
        $count = array();
        foreach ($items as $artist) {
            if ($artist instanceof self) {
                $key = mb_strtolower($artist->name, 'UTF-8');
                $count[$key] = isset($count[$key]) ? $count[$key] + 1 : 1;
            }
        }
        foreach ($items as $artist) {
            if ($artist instanceof self) {
                $artist->displayName = $count[mb_strtolower($artist->name, 'UTF-8')] > 1 ? $artist->qualifiedName : $artist->name;
            }
        }
        return $artists;
    }

    /**
     * Get full URL path for artist
     *
     * @return string Full URL path (e.g., /artist-name-p123.html)
     */
    public function getUrl()
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        return $router->assemble(
            array('id' => $this->id, 'seo' => $this->url),
            'artist',
            true
        );
    }
}
