<?php

/**
 * Album
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 11 October, 2010
 * @package default
 **/

class Model_Album_Container
{
    /**
     * What a page shows next to the title for each release type; nothing for an album.
     */
    public const RELEASE_TYPE_LABELS = array(
        'album'       => null,
        'ep'          => 'EP',
        'mixtape'     => 'mixtape',
        'compilation' => 'kompilacja',
        'beat_tape'   => 'beat tape',
        'single'      => 'singiel',
        'other'       => null,
    );

    /**
     * The media a release came out on, in the order a page lists them.
     */
    public const MEDIA = array(
        'media_cd'      => 'CD',
        'media_lp'      => 'LP',
        'media_mc'      => 'MC',
        'media_digital' => 'cyfrowo',
    );

    public $id;
    public $title;
    public $artist;
    public $releaseDate;
    public $cover;
    public $autoDescription = null;

    /** album, ep, mixtape, compilation, beat_tape, single or other */
    public $releaseType = 'album';
    /** The type as a page shows it ("EP"), or null for an album */
    public $releaseTypeLabel;
    /** The media it came out on, as a page lists them: CD, LP, MC, cyfrowo */
    public $media = array();
    /** False for a nielegal, a release that came out without a publisher's licence */
    public $legal = true;
    /** day, month or year: how much of releaseDate is known */
    public $releaseDatePrecision;
    /** Whether the release is only announced; null where the database does not say */
    public $announced;

    public function __construct($params, $full = false)
    {
        $configApp = Zend_Registry::get('Config_App');

        $this->id = $params['alb_id'];
        $this->title = $params['title'];

        if (!empty($params['art_id'])) {
            $artistApi = Model_Artist_Api::getInstance();
            $this->artist = $artistApi->find($params['art_id']);
        }

        // No label: labelid NULL, or until #55's migration retires it, the placeholder label
        // "BRAK" (27) that stood for none.
        $this->label = null;
        if (!empty($params['lab_id'])) {
            $label = Model_Label_Api::getInstance()->find($params['lab_id']);
            if ($label->name != 'BRAK') {
                $this->label = $label;
            }
        }

        if (!empty($params['legal'])) {
            $this->legal = ($params['legal'] == 'y') ? true : false;
        }

        $this->releaseType = self::releaseTypeOf($params);
        $this->releaseTypeLabel = self::RELEASE_TYPE_LABELS[$this->releaseType];
        $this->media = self::mediaOf($params);

        $this->releaseDate = $params['year'];
        $this->year = substr($params['year'], 0, 4);
        $this->releaseDatePrecision = !empty($params['release_date_precision'])
            ? $params['release_date_precision']
            : Jkl_Tools_Date::precisionOf($this->releaseDate);
        $this->releaseDateNormalized = Jkl_Tools_Date::getNormalDate($this->releaseDate, $this->releaseDatePrecision);
        // Before migration 0015 there is no announced column, and the date decides.
        $this->announced = isset($params['announced']) ? (bool) $params['announced'] : null;

        $this->catalogNumber = self::catalogNumberOf($params);

        if (!empty($params['epfor'])) {
            $this->epFor = $params['epfor'];
        }

        if (!empty($params['singiel'])) {
            $this->ep = $params['singiel'];
        }

        // TODO: users api
        if (!empty($params['alb_addedby'])) {
            $this->addedBy = $params['alb_addedby'];
        }
        if (!empty($params['alb_added'])) {
            $this->added = $params['alb_added'];
        }
        if (!empty($params['alb_viewed'])) {
            $this->views = $params['alb_viewed'];
        }
        if (!empty($params['updated'])) {
            $this->updated = $params['updated'];
        }

        if (!empty($params['cover'])) {
            $this->cover = $configApp['paths']['albumCoverPath'] . $params['cover'];
            $this->thumbnail = $configApp['paths']['albumThumbnailPath'] . substr($params['cover'], 0, -4) . $configApp['paths']['albumThumbnailSuffix'];
        } else {
            $this->cover = $configApp['paths']['albumCoverPath'] . 'cd.png';
            $this->thumbnail = $configApp['paths']['albumThumbnailPath'] . 'cd.png';
        }

        if (!empty($params['rating'])) {
            $this->rating = number_format($params['rating'], 1);
        } else {
            $this->rating = '--';
        }
        if (!empty($params['updated'])) {
            $this->updated = $params['updated'];
        }

        if (!empty($params['status'])) {
            $this->status = $params['status'];
        }

        if ($full) {
            $this->tracklist = $params['tracklist'];
            $this->description = $params['description'];
            $this->eps = $params['eps'];
            $this->duration = $params['duration'];
            $this->voteCount = $params['votecount'];
        }

        $this->url = Jkl_Tools_Url::createUrl($this->title);
    }

    /**
     * Get full URL path for album
     *
     * @return string Full URL path (e.g., /artist-album-a123.html)
     */
    public function getUrl()
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        return $router->assemble(
            array('id' => $this->id, 'seo' => $this->artist->url . '-' . $this->url),
            'album',
            true
        );
    }

    /**
     * The release type of a row: release_type, or before migration 0014 added it, an EP for any
     * album that singiel or epfor marked, as the page showed them.
     *
     * @return string
     */
    public static function releaseTypeOf(array $params)
    {
        if (!empty($params['release_type']) && array_key_exists($params['release_type'], self::RELEASE_TYPE_LABELS)) {
            return $params['release_type'];
        }
        return (!empty($params['singiel']) || !empty($params['epfor'])) ? 'ep' : 'album';
    }

    /**
     * The media a row says the release came out on, in the page's order.
     *
     * @return string[]
     */
    public static function mediaOf(array $params)
    {
        $media = array();
        foreach (self::MEDIA as $column => $label) {
            if (!empty($params[$column])) {
                $media[] = $label;
            }
        }
        return $media;
    }

    /**
     * The catalog number a page shows: the CD's, else the LP's, the cassette's or the digital
     * release's, so a release without a CD still shows its number.
     *
     * @return string|null
     */
    public static function catalogNumberOf(array $params)
    {
        foreach (array('catalog_cd', 'catalog_lp', 'catalog_mc', 'catalog_digital') as $column) {
            if (!empty($params[$column])) {
                return $params[$column];
            }
        }
        return null;
    }

    /**
     * Checks if album is announced, or already released: what the announced column says, or
     * without it, whether the date is still to come.
     */
    public function isAnnounced()
    {
        if (null !== $this->announced) {
            return $this->announced;
        }
        return ($this->releaseDate >= date('Y-m-d'));
    }
}
// [premier] =>
// [media_mc] => 1
// [catalog_mc] =>
// [media_cd] => 1
// [media_lp] => 0
// [catalog_lp] =>
// [artistabout] =>
// [notes] =>
