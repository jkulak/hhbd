<?php

/**
 * Song
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package default
 **/

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
        if (!empty($params['art_id'])) {
            $this->artist = new Jkl_List();
            $artistObj = new stdClass();
            $artistObj->id = $params['art_id'];
            $artistObj->name = $params['art_name'];
            $artistObj->url = Jkl_Tools_Url::createUrl($params['art_name']);
            $this->artist->add($artistObj);
        }
    }

    public function url()
    {
        return Jkl_Tools_Url::createUrl($this->title);
    }
}
