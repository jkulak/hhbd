<?php

/**
 * Song
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package default
 **/

#[\AllowDynamicProperties]
class Model_Song_Container
{
    public $lyrics = '';

    public function __construct($params, $full = false)
    {
        $this->id = $params['song_id'];
        $this->title = !empty($params['song_title']) ? $params['song_title'] : $params['title'];

        $this->track = (!empty($params['track']) ? $params['track'] : null);

        if (!empty($params['length'])) {
            $this->duration = sprintf("%02.2d:%02.2d", floor($params['length'] / 60), $params['length'] % 60);
        } else {
            $this->duration = null;
        }
        $this->bpm = $params['bpm'];

        if (!empty($params['featuring'])) {
            $this->featuring = $params['featuring'];
        }

        if (!empty($params['music'])) {
            $this->music = $params['music'];
        }

        if (!empty($params['scratch'])) {
            $this->scratch = $params['scratch'];
        }

        if (!empty($params['artist'])) {
            $this->artist = $params['artist'];
        }

        if (!empty($params['youtube_url'])) {
            $this->youTubeUrl = $params['youtube_url'];
        }

        $this->youTubeUrlFlag = $params['youtube_url_flag'];

        if (!empty($params['featured'])) {
            $this->featured = $params['featured'];
        }

        if (!empty($params['lyrics'])) {
            $this->lyrics = $params['lyrics'];
        }

        if (!empty($params['albumArtist'])) {
            $this->albumArtist = $params['albumArtist'];
        }

        // Views count
        if (!empty($params['song_views'])) {
            $this->views = $params['song_views'];
        }

        // Comment count
        if (isset($params['comment_count'])) {
            $this->commentCount = $params['comment_count'];
        }

        // Album data for list display
        if (!empty($params['alb_id'])) {
            $configApp = Zend_Registry::get('Config_App');
            $this->album = new stdClass();
            $this->album->id = $params['alb_id'];
            $this->album->title = $params['alb_title'];
            $this->album->cover = $params['alb_cover'];
            $this->album->url = Jkl_Tools_Url::createUrl($params['alb_title']);
            if (!empty($params['alb_cover'])) {
                $this->album->thumbnail = $configApp['paths']['albumThumbnailPath'] . substr($params['alb_cover'], 0, -4) . $configApp['paths']['albumThumbnailSuffix'];
            } else {
                $this->album->thumbnail = $configApp['paths']['albumThumbnailPath'] . 'cd.png';
            }
        }

        // Artist data for list display (overwrite string value with proper object)
        // Only create stdClass artist if we don't already have a proper artist list
        if (!empty($params['art_id']) && empty($this->artist)) {
            $this->artist = new Jkl_List();
            $artistObj = new stdClass();
            $artistObj->id = $params['art_id'];
            $artistObj->name = $params['art_name'];
            $artistObj->url = Jkl_Tools_Url::createUrl(Model_Artist_Container::qualifiedNameOf(
                $params['art_name'],
                isset($params['art_disambiguation']) ? $params['art_disambiguation'] : ''
            ));
            $this->artist->add($artistObj);
        }
    }

    /** The video's YouTube id, for the player (#149), or null when the song has no video */
    public function getYouTubeId()
    {
        return isset($this->youTubeUrl) ? self::youTubeIdOf($this->youTubeUrl) : null;
    }

    /**
     * The id in a YouTube address, in any form the songs keep: /v/ID, the Flash player's that no
     * browser plays any more, on most of them; /embed/ID; watch?v=ID; youtu.be/ID. Null for
     * anything else.
     */
    public static function youTubeIdOf($url)
    {
        $id = '([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])';
        if (preg_match('~youtube(?:-nocookie)?\.com/(?:v/|embed/|watch\?(?:[^#]*?&(?:amp;)?)?v=)' . $id . '~', (string) $url, $m)
            || preg_match('~youtu\.be/' . $id . '~', (string) $url, $m)) {
            return $m[1];
        }
        return null;
    }

    public function url()
    {
        return Jkl_Tools_Url::createUrl($this->title);
    }

    /**
     * Get full URL path for song
     *
     * @return string Full URL path (e.g., /artist-song-s123.html)
     */
    public function getUrl()
    {
        $prefix = !empty($this->artist->items[0]) ? $this->artist->items[0]->url . '-' : '';
        $router = Zend_Controller_Front::getInstance()->getRouter();
        return $router->assemble(
            array('id' => $this->id, 'seo' => $prefix . $this->url()),
            'song',
            true
        );
    }

    /**
     * Get full URL path for this song's album
     *
     * @return string Full URL path or empty string if no album
     */
    public function getAlbumUrl()
    {
        if (empty($this->album->url)) {
            return '';
        }
        $prefix = !empty($this->artist->items[0]) ? $this->artist->items[0]->url . '-' : '';
        $router = Zend_Controller_Front::getInstance()->getRouter();
        return $router->assemble(
            array('id' => $this->album->id, 'seo' => $prefix . $this->album->url),
            'album',
            true
        );
    }
}
