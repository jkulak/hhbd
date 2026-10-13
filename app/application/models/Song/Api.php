<?php

/**
 * Song Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 November, 2010
 * @package hhbd
 **/

class Model_Song_Api extends Jkl_Model_Api
{
    private static $_instance;

    public const LYRICS_ACTION_ADD = "add";
    public const LYRICS_ACTION_EDIT = "edit";
    public const LYRICS_ACTION_DELETE = "delete";

    public const MINIMUM_LYRICS_LENGTH = 20;

    /**
     * A song a visitor is shown is on a published album with an artist: one on no such album
     * has nothing to show it with (#37), and one on unpublished albums only waits with them
     * for an admin (#168). Follows a song id column, as "t1.id " . SHOWN.
     */
    /**
     * A song whose page has something of its own (#243): lyrics, or someone credited with its
     * music, a guest verse or the scratches. A page with none of them, a title and perhaps a
     * video, asks search engines not to index it and stays out of the sitemap; the condition
     * is on the songs table aliased t1, for both.
     */
    public const HAS_CONTENT = "(t1.lyrics <> ''
        OR EXISTS (SELECT 1 FROM music_lookup m WHERE m.songid = t1.id)
        OR EXISTS (SELECT 1 FROM feature_lookup f WHERE f.songid = t1.id)
        OR EXISTS (SELECT 1 FROM scratch_lookup x WHERE x.songid = t1.id))";

    private const SHOWN = 'IN (SELECT l.songid FROM album_lookup l JOIN albums al ON al.id = l.albumid
                               WHERE al.status = ' . Model_Album_Api::PUBLISHED . '
                                 AND EXISTS (SELECT 1 FROM album_artist_lookup c JOIN artists a ON a.id = c.artistid WHERE c.albumid = l.albumid))';

    // private $_artistTypes = array(
    //   'add' => Model_Song_Container::LYRICS_ACTION_ADD,
    //   'edit' => Model_Song_Container::LYRICS_ACTION_EDIT,
    //   'delete' => Model_Song_Container::LYRICS_ACTION_DELETE);

    /**
     * Singleton instance
     *
     * @return Model_Song_Api
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Creates object and fetches the list from database result
     */
    private function _getList($query, array $bind = array())
    {
        $result = $this->_db->fetchAll($query, $bind);
        $list = new Jkl_List();
        foreach ($result as $params) {
            $list->add(new Model_Song_Container($params));
        }
        return $list;
    }

    /**
     * The query for exactly these songs, in this order: the ids come sorted from a narrow
     * query, and the rows by primary key, so no sort carries the lyrics (#69).
     *
     * @param string $query SELECT ... FROM songs t1 ..., with no WHERE
     * @param array $ids
     */
    private function _byIds($query, array $ids)
    {
        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            return $query . ' WHERE 1 = 0';
        }
        $list = implode(', ', $ids);
        return $query . ' WHERE t1.id IN (' . $list . ') ORDER BY ' . Jkl_Db::inOrder('t1.id', $ids);
    }

    /**
     * Get list of songs with properly populated artist containers
     * This method extends _getList() by loading artist data for each song
     *
     * @param string $query SQL query to fetch songs
     * @return Jkl_List List of Song containers with populated artists
     */
    private function _getListWithArtists($query)
    {
        $result = $this->_db->fetchAll($query);
        // Every song's artists in one query, not one for each (#255)
        $credits = Model_Artist_Api::getInstance()->getSongsCredits(array_column($result, 'song_id'));
        $list = new Jkl_List();

        foreach ($result as $params) {
            $params['artist'] = $credits[(int) $params['song_id']]['artist'];
            $list->add(new Model_Song_Container($params));
        }

        return $list;
    }

    /** Whether song $id has a page a visitor may open, for the page to ask before it builds it (#147, #168) */
    /** Whether a song's page has something of its own: HAS_CONTENT */
    public function hasContent($id)
    {
        return !empty($this->_db->fetchAll('SELECT 1 FROM songs t1 WHERE t1.id = ? AND ' . self::HAS_CONTENT, array((int) $id)));
    }

    public function exists($id)
    {
        return !empty($this->_db->fetchAll('SELECT 1 FROM songs t1 WHERE t1.id = ? AND t1.id ' . self::SHOWN, array((int) $id)));
    }

    public function find($id, $full = false)
    {
        $id = intval($id);
        $query = 'select *, id as song_id from songs where id=' . $id;
        $result = $this->_db->fetchAll($query);
        $credits = Model_Artist_Api::getInstance()->getSongsCredits(array($id));
        return $this->_song($result[0], $credits[$id], Model_Album_Api::getInstance()->getSongAlbums($id, null), $full);
    }

    /**
     * A song from its row, its credits (Model_Artist_Api::getSongsCredits()) and its albums,
     * alike for its own page and for an album's tracklist (#255): the album's artist it is
     * shown under, who is its artist when it names none, and on its own page ($full) the
     * video it has none of yet.
     */
    private function _song(array $params, array $credits, Jkl_List $albums, $full)
    {
        $id = (int) $params['song_id'];
        $params['featuring'] = $credits['featuring'];
        $params['music'] = $credits['music'];
        $params['scratch'] = $credits['scratch'];
        $params['artist'] = $credits['artist'];
        $params['featured'] = $albums;

        $albumArtist = null;
        $max = isset($params['featured']->items) ? count($params['featured']->items) : 0;
        if ($max > 0) {
            for ($i = 0; $i < $max; $i++) {
                if ($params['featured']->items[$i]->artist->name != 'V/A') {
                    $albumArtist = $params['featured']->items[$i]->artist;
                    // we set artists for this song, so we can exit
                    break(1);
                }
            }
        } else {
            // song is not featured on any album for some reason? send notification email
            Zend_Registry::get('Logger')->info('Song ' . $id . ' is not featured on any album.');
        }

        if (null === $albumArtist) {
            if ($max > 0) {
                $albumArtist = new Model_Artist_Container(array('name' => $params['featured']->items[0]->title, 'art_id' => $params['featured']->items[0]->id));
            }
        }

        $params['albumArtist'] = $albumArtist;

        // assign album artist to a song, if it doesnt have artist assigned
        if (null !== $albumArtist && (!isset($params['artist']->items) || count($params['artist']->items) < 1)) {
            $params['artist']->add($albumArtist);
        }

        if ($params['youtube_url'] === null and $full) {
            $searchTerms = (isset($albumArtist) ? $albumArtist->name . ' ' : '') . $params['title'];
            $rep = array('/', '&', '?', '-');
            $searchTerms = str_replace($rep, ' ', $searchTerms);
            $url = $this->_getYouTubeUrl($searchTerms);
            $this->_db->query('UPDATE songs SET youtube_url = ? WHERE id = ?', array(false === $url ? '' : (string) $url, (int) $id));
            $params['youtube_url'] = $url;
            // Zend_Registry::get('Logger')->info('Save YouTube ' . $params['title'] . '(' . $id . '): ' . $url);
        }

        $item = new Model_Song_Container($params);
        return $item;
    }

    public function getTracklist($id)
    {
        return $this->getTracklists(array($id))[(int) $id];
    }

    /**
     * The tracklists of these albums: the songs' rows with their places, then everyone credited
     * on them and the albums they are on, a query each for all the albums rather than six for
     * each track (#255). A song's page shows the tracklist of every album it is on.
     *
     * @param int[] $ids albums' ids
     * @return array album id => Jkl_List of its songs, in their order
     */
    public function getTracklists(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $positions = array_fill_keys($ids, array());
        $songs = array();
        if (!empty($ids)) {
            $query = 'SELECT t1.*, t1.id AS song_id, t2.albumid AS on_album, t2.disc AS on_disc, t2.track AS on_track ' .
                'FROM songs AS t1, album_lookup AS t2 ' .
                'WHERE (t1.id=t2.songid AND t2.albumid IN (' . implode(', ', $ids) . '))';
            foreach ($this->_db->fetchAll($query) as $row) {
                list($disc, $track) = self::trackPosition(array('disc' => $row['on_disc'], 'track' => $row['on_track']));
                $albumId = (int) $row['on_album'];
                unset($row['on_album'], $row['on_disc'], $row['on_track']);
                $songs[(int) $row['song_id']] = $row;
                $positions[$albumId][] = array('disc' => $disc, 'track' => $track, 'song_id' => (int) $row['song_id']);
            }
        }
        $credits = Model_Artist_Api::getInstance()->getSongsCredits(array_keys($songs));
        $albums = Model_Album_Api::getInstance()->getSongsAlbums(array_keys($songs));

        $tracklists = array();
        foreach ($positions as $albumId => $places) {
            usort($places, function ($a, $b) {
                return array($a['disc'], $a['track']) <=> array($b['disc'], $b['track']);
            });
            $multiDisc = count(array_unique(array_column($places, 'disc'))) > 1;
            $tracklist = new Jkl_List();
            foreach ($places as $position) {
                $songId = $position['song_id'];
                $song = $this->_song($songs[$songId], $credits[$songId], $albums[$songId], false);
                $song->track = self::trackLabel($position['disc'], $position['track'], $multiDisc);
                $tracklist->add($song);
            }
            $tracklists[$albumId] = $tracklist;
        }
        return $tracklists;
    }

    /**
     * The disc and the position on it of an album_lookup row: from the disc column once it
     * exists, and until then from track, which held disc * 100 + position for an album of
     * several discs (track 203 is the third track of the second disc).
     *
     * @return int[] array(disc, track)
     */
    public static function trackPosition(array $row)
    {
        $track = (int) $row['track'];
        if (isset($row['disc'])) {
            return array((int) $row['disc'], $track);
        }
        return $track >= 100 ? array(intdiv($track, 100), $track % 100) : array(1, $track);
    }

    /**
     * A track's number as the tracklist shows it: "7" on a single disc, "2-07" on an album of
     * several.
     *
     * @return string
     */
    public static function trackLabel($disc, $track, $multiDisc)
    {
        return $multiDisc ? sprintf('%d-%02d', $disc, $track) : (string) $track;
    }

    /** An album's length in seconds as its page shows it, "45:07", or 0 for none */
    public static function formatDuration($seconds)
    {
        return $seconds > 0 ? sprintf('%02.2d:%02.2d', floor($seconds / 60), $seconds % 60) : 0;
    }

    public function getMostPopularByArtist($id, $limit = 10)
    {
        $id = intval($id);
        $limit = intval($limit);
        // The ids first, sorted without the lyrics, then the rows by id (#69).
        $ids = array_column($this->_db->fetchAll(
            'SELECT t1.id FROM songs t1 JOIN artist_lookup t2 ON t2.songid = t1.id
              WHERE t2.artistid = ' . $id . ' AND t1.id ' . self::SHOWN . '
              ORDER BY t1.viewed DESC' . (($limit) ? ' LIMIT ' . $limit : '')
        ), 'id');
        return $this->_getList($this->_byIds('SELECT *, t1.id as song_id FROM songs t1', $ids));
    }

    public function getMostPopular($limit = 10)
    {
        $limit = intval($limit);
        // The most viewed ids first, from the index on viewed, then each song with one album
        // and one of its artists. Grouping every song's join to pick those read the whole
        // catalogue through a temporary table on disk on each call (#69).
        $ids = array_column($this->_db->fetchAll('SELECT t1.id FROM songs t1 WHERE t1.id ' . self::SHOWN . ' ORDER BY t1.viewed DESC' . (($limit) ? ' LIMIT ' . $limit : '')), 'id');
        $query = 'SELECT *, t1.id as song_id, t1.title as song_title, t1.viewed as song_views, t3.id as alb_id, t3.cover as alb_cover, t3.title as alb_title, t5.id as art_id, t5.name as art_name, t5.disambiguation as art_disambiguation, ' .
                  '(SELECT COUNT(*) FROM hhb_comments WHERE com_object_id = t1.id AND com_object_type = \'s\') as comment_count ' .
                  'FROM songs t1 ' .
                  'LEFT JOIN albums t3 ON t3.id = (SELECT MIN(l.albumid) FROM album_lookup l WHERE l.songid = t1.id) ' .
                  'LEFT JOIN artists t5 ON t5.id = (SELECT MIN(c.artistid) FROM album_artist_lookup c WHERE c.albumid = t3.id)';
        return $this->_getListWithArtists($this->_byIds($query, $ids));
    }

    /**
     * YouTube API v2 (Zend_Gdata_YouTube) was deprecated and removed.
     * This method now returns false. YouTube URLs should be added manually via backoffice.
     */
    private function _getYouTubeUrl($searchTerms = 'polski hip-hop')
    {
        // YouTube API v2 is no longer available
        return false;
    }

    public function getLike($like, $limit = 25, $page = 1)
    {
        $limit = intval($limit);
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;

        $query = "SELECT *, t1.id as song_id
              FROM songs t1
              WHERE " . $this->_db->searchLike('t1.title') . " AND t1.id " . self::SHOWN . "
              ORDER BY t1.viewed DESC" .
                  (($limit != null) ? ' LIMIT ' . $limit : '') .
                  ' OFFSET ' . ($page * $limit);
        return $this->_getList($query, array(Jkl_Db::contains($like)));
    }

    public function getLikeCount($like = '')
    {
        $query = "SELECT count(*) as count
              FROM songs AS t1
              WHERE " . $this->_db->searchLike('t1.title') . " AND t1.id " . self::SHOWN;
        $result = $this->_db->fetchAll($query, array(Jkl_Db::contains($like)));
        return intval($result[0]['count']);
    }

    public function updateView($id)
    {
        $id = intval($id);
        $query = 'UPDATE songs SET viewed=viewed+1 WHERE id=' . $id;
        $this->_db->query($query);
    }

    /**
     * Saves songs lyrics to database
     *
     * @return number of affected rows (0 - no changes made, 1 - changes saved)
     * @author Kuba
     **/
    public function saveLyrics($songId, $lyrics, $userId, $action = self::LYRICS_ACTION_EDIT)
    {
        if (strlen($lyrics) < self::MINIMUM_LYRICS_LENGTH) {
            $action = self::LYRICS_ACTION_DELETE;
        }

        // Bound, never escaped into the query: the id came straight from the request and went
        // into the INSERT as it was, and the lyrics were escaped the way only MariaDB read (#230).
        $id = intval($songId);
        $result = $this->_db->query('UPDATE songs SET lyrics = ? WHERE id = ?', array($lyrics, $id));

        // if 1 row was updated save information who did the lyrics editing
        if ($result->rowCount() == 1) {
            $this->_db->query(
                'INSERT INTO hhb_user_lyrics_edit (ule_lyrics_id, ule_user_id, ule_action_type, ule_lyrics, ule_action_timestamp) VALUES (?, ?, ?, ?, ?)',
                array($id, intval($userId), $action, $lyrics, date('Y-m-d H:i:s'))
            );
        }

        /*
          TODO 2011-02-02 flush memcached for this song here
        */

        return $result->rowCount();
    }

    /**
    * Returns list of most recent songs
    * used for: sitemaps
    **/
    /**
     * The songs that have a page, newest first, for the sitemap (#147). A song not shown (SHOWN)
     * answers 404, so it is no address to hand a search engine.
     */
    public function getSitemap($limit)
    {
        $query = 'SELECT *, t1.id AS song_id, t1.title AS sng_title
              FROM songs t1
              WHERE t1.id ' . self::SHOWN . ' AND ' . self::HAS_CONTENT . '
              ORDER BY t1.added DESC
              LIMIT ' . intval($limit);
        return $this->_getList($query);
    }

    /** The songs a visitor can open, those on a shown album, for the about page's figures (#218) */
    public function getShownCount()
    {
        $result = $this->_db->fetchAll('SELECT COUNT(*) AS n FROM songs t1 WHERE t1.id ' . self::SHOWN);
        return (int) $result[0]['n'];
    }

    public function getRecent($limit = 20)
    {
        $limit = intval($limit);
        $query = "SELECT *, t1.id AS song_id, t1.title AS sng_title
              FROM songs t1
              WHERE t1.id " . self::SHOWN . "
              ORDER BY t1.added DESC" .
                  (($limit != null) ? ' LIMIT ' . $limit : '');
        return $this->_getList($query);
    }

    /** A song's id as a request carries it, a whole number from 1, or null for anything else (#178) */
    public static function idOf($value)
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || 1 !== preg_match('/^[1-9][0-9]{0,9}$/', $value)) {
            return null;
        }
        return (int) $value;
    }

    /**
     * Counts one "this is not the video of this song" for song $id
     *
     * @return int how many the song has now
     */
    public function flagVideo($id)
    {
        $this->_db->query('UPDATE songs SET youtube_url_flag = youtube_url_flag + 1 WHERE id = ?', array((int) $id));
        return $this->videoFlags($id);
    }

    /** How many times song $id was reported not to have its own video */
    public function videoFlags($id)
    {
        $rows = $this->_db->fetchAll('SELECT youtube_url_flag FROM songs WHERE id = ?', array((int) $id));
        return empty($rows) ? 0 : (int) $rows[0]['youtube_url_flag'];
    }
}
