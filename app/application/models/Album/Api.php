<?php

/**
 * Album Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/

// extends Api
class Model_Album_Api extends Jkl_Model_Api
{
    private static $_instance;

    /**
     * Singleton instance
     *
     * @return Model_Album_Api
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
    public function getList($query)
    {
        $albums = new Jkl_List();
        foreach ($this->_withCredits($this->_db->fetchAll($query)) as $params) {
            $albums->add(new Model_Album_Container($params));
        }
        return $albums;
    }

    /**
     * One row per album, in the order the query gave them, though a query joining
     * album_artist_lookup gives one per credited artist; each with every artist credited on
     * it, so an album by two artists is listed once and names both (#58).
     */
    private function _withCredits(array $rows)
    {
        $albums = array();
        foreach ($rows as $row) {
            if (!isset($row['alb_id'])) {
                $albums[] = $row;
            } elseif (!isset($albums[(int) $row['alb_id']])) {
                $albums[(int) $row['alb_id']] = $row;
            }
        }
        $credits = $this->getCredits(array_keys($albums));
        $covers = $this->getCovers(array_keys($albums));
        foreach ($albums as $id => $row) {
            if (isset($credits[$id])) {
                $albums[$id]['credits'] = $credits[$id];
            }
            if (isset($covers[$id])) {
                $albums[$id]['covers'] = $covers[$id];
            }
        }
        return array_values($albums);
    }

    /**
     * Each album's cover files from album_covers (#60), one per variant: the main cover's, and
     * of several the newest.
     *
     * @param int[] $albumIds
     * @return array album id => variant => array('path', 'width', 'height')
     */
    public function getCovers(array $albumIds)
    {
        $albumIds = array_filter(array_unique(array_map('intval', $albumIds)));
        if (empty($albumIds)) {
            return array();
        }
        $rows = $this->_db->fetchAll(
            "SELECT albumid, variant, path, width, height FROM album_covers
              WHERE albumid IN (" . implode(',', $albumIds) . ")
              ORDER BY albumid, variant, main = 'y', id"
        );
        $covers = array();
        foreach ($rows as $row) {
            // The last one per variant wins: the main cover, then the newest.
            $covers[(int) $row['albumid']][$row['variant']] = array(
                'path'   => $row['path'],
                'width'  => (int) $row['width'],
                'height' => (int) $row['height'],
            );
        }
        return $covers;
    }

    /**
     * The artists credited on each album, as creditsByAlbum() orders them.
     *
     * @param int[] $albumIds
     * @return array album id => credits
     */
    public function getCredits(array $albumIds)
    {
        $albumIds = array_filter(array_unique(array_map('intval', $albumIds)));
        if (empty($albumIds)) {
            return array();
        }
        // Joined to artists, as the lists always were, so a credit naming no artist is skipped.
        $rows = $this->_db->fetchAll(
            'SELECT l.* FROM album_artist_lookup l JOIN artists a ON a.id = l.artistid
              WHERE l.albumid IN (' . implode(',', $albumIds) . ')'
        );
        return self::creditsByAlbum($rows);
    }

    /**
     * album_artist_lookup rows as each album's credits: main artists before featured ones, then
     * by position once migration 0015 adds the column, and by artist id, the order the pages
     * have always picked the first artist in. Without the columns every credit is a main one
     * under the artist's own name.
     *
     * @return array album id => list of array('artistid', 'role', 'position', 'credited_as')
     */
    public static function creditsByAlbum(array $rows)
    {
        $byAlbum = array();
        foreach ($rows as $row) {
            $byAlbum[(int) $row['albumid']][] = array(
                'artistid'    => (int) $row['artistid'],
                'role'        => (isset($row['role']) && 'featured' === $row['role']) ? 'featured' : 'main',
                'position'    => isset($row['position']) ? (int) $row['position'] : 1,
                'credited_as' => (isset($row['credited_as']) && '' !== trim($row['credited_as'])) ? $row['credited_as'] : null,
            );
        }
        foreach ($byAlbum as $id => $credits) {
            usort($credits, function ($a, $b) {
                return array('featured' === $a['role'], $a['position'], $a['artistid'])
                    <=> array('featured' === $b['role'], $b['position'], $b['artistid']);
            });
            $byAlbum[$id] = $credits;
        }
        return $byAlbum;
    }

    public function find($id, $full = false)
    {
        $id = intval($id);
        $query = "SELECT *, t1.id as alb_id, t1.labelid AS lab_id, t1.epfor as epforid, t1.added as alb_added, t1.addedby as alb_addedby, t1.viewed as alb_viewed, t3.id as art_id " .
        "FROM albums t1, album_artist_lookup t2, artists t3 " .
        "WHERE (t3.id=t2.artistid AND t2.albumid=t1.id AND t1.id='" . $id . "')";
        $result = $this->_db->fetchAll($query);
        $params = $result[0];
        $credits = $this->getCredits(array($id));
        if (isset($credits[$id])) {
            $params['credits'] = $credits[$id];
        }
        $covers = $this->getCovers(array($id));
        if (isset($covers[$id])) {
            $params['covers'] = $covers[$id];
        }
        if ($full) {
            $params['tracklist'] = Model_Song_Api::getInstance()->getTracklist($id);
            $params['eps'] = $this->getEps($id);
            if (!empty($params['epforid'])) {
                $params['epfor'] = $this->getEpFor($params['epforid']);
            }
            $params['duration'] = Model_Song_Api::getInstance()->getAlbumDuration($id);
            $params['votecount'] = Model_Rating_Api::getInstance()->getAlbumVoteCount($id);
            $params['rating'] = Model_Rating_Api::getInstance()->getAlbumRating($id);
        }
        $item = new Model_Album_Container($params, $full);
        return $item;
    }

    private function getEpFor($id)
    {
        $id = intval($id);
        return Model_Album_Api::getInstance()->find($id);
    }

    private function getEps($id)
    {
        $id = intval($id);
        $query = 'SELECT id FROM albums WHERE epfor=' . $id . ' ORDER BY year DESC';
        $result = $this->_db->fetchAll($query);
        $eps = new Jkl_List();
        $albumApi = Model_Album_Api::getInstance();
        foreach ($result as $params) {
            $ep = $albumApi->find($params['id']);
            $eps->add($ep);
        }
        return $eps;
    }

    public function getLike($like = '', $limit = 20, $page = 1)
    {
        $like = Jkl_Db::escape($like);
        $limit = intval($limit);
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;
        $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
          'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t3.title LIKE "%' . $like . '%" AND t1.id=t2.artistid AND t2.albumid=t3.id) ' .
          'GROUP BY t3.id ' .
          'ORDER BY t3.viewed DESC' .
          (($limit != null) ? ' LIMIT ' . $limit : '') .
          ' OFFSET ' . ($page * $limit);
        return $this->getList($query);
    }

    public function getLikeCount($like = '')
    {
        $like = Jkl_Db::escape($like);
        $query = "SELECT count(*) as count
              FROM albums AS t1
              WHERE t1.title LIKE '%$like%'";
        $result = $this->_db->fetchAll($query);
        return intval($result[0]['count']);
    }
    /**
     * Gets list of popular albums by views count, including incomming albums
     *
     * @param integer $count Number of albums to be returned
     * @return Jkl_List
     * @author Kuba
     */
    public function getPopular($count = 20)
    {
        $count = intval($count);
        $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
          'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id) ' .
          'GROUP BY t3.id ' .
          'ORDER BY t3.viewed DESC ' .
          'LIMIT ' . $count;
        return $this->getList($query);
    }

    public function getBest($count = 10)
    {
        $count = intval($count);
        $query = 'SELECT *, t1.id AS alb_id, t2.rating AS rating, t3.artistid AS art_id, t4.id AS lab_id ' .
          'FROM albums t1 LEFT JOIN labels t4 ON t4.id=t1.labelid, ratings_avg t2, album_artist_lookup t3 ' .
          'WHERE (t1.id=t2.albumid AND t3.albumid=t1.id) ' .
          'GROUP BY t1.id ' .
          'ORDER BY t2.rating DESC ' .
          'LIMIT ' . $count;
        return $this->getList($query);
    }

    /**
    * Returns list of released albums sorted by release date, decreasing (from most recent to oldest)
    */
    public function getNewest($count = 20, $page = 1)
    {
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;
        $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
          'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t3.announced=0) ' .
          'GROUP BY t3.id ' .
          'ORDER BY t3.year DESC ' .
          'LIMIT ' . $count . ' ' .
          'OFFSET ' . ($page * $count);
        return $this->getList($query);
    }

    public function getAnnounced($count = 20, $page = 1)
    {
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;
        $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id AS lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
          'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t3.announced=1 AND t3.year>=CURDATE()) ' .
          'GROUP BY t3.id ' .
          'ORDER BY t3.year ASC ' .
          'LIMIT ' . $count . ' ' .
          'OFFSET ' . ($page * $count);
        return $this->getList($query);
    }

    public function getFirstLetters()
    {
        $query = 'SELECT DISTINCT(SUBSTR(title, 1,1)) as name from albums order by name ASC';
        return  $this->_db->fetchAll($query);
    }

    public function getArtistsAlbums($id, $exclude = array(), $count = 10, $order = 'viewed')
    {
        $id = intval($id);
        $count = intval($count);
        // $order in array

        $excludeCondition = '';
        if (!empty($exclude)) {
            foreach ($exclude as $key => $value) {
                $excludeCondition .= ' AND t3.id<>' . intval($value) . ' ';
            }
        }
        $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
          'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t1.id="' . $id . '"' .
          $excludeCondition .
          ') ' .
          'ORDER BY t3.' . $order . ' DESC ' .
          (($count) ? 'LIMIT ' . $count : '');
        return self::getList($query);
    }

    public function getArtistsAlbumsCount($id)
    {
        $id = intval($id);
        $query = "SELECT count(*) as count
              FROM albums t1, album_artist_lookup t2, band_lookup t3
              WHERE (t3.`bandid`=t2.`artistid` AND t3.`artistid`=$id AND t1.`id`=t2.`albumid`)";
        $result = $this->_db->fetchAll($query);
        $projectAlbums = $result[0]['count'];

        $query = "SELECT count(*) as count
              FROM albums t1, album_artist_lookup t2
              WHERE (t1.id=t2.albumid AND t2.artistid=$id);";
        $result = $this->_db->fetchAll($query);
        $albumCount = $result[0]['count'];

        return $albumCount + $projectAlbums;
    }

    public function getLabelsAlbums($id, $exclude = array(), $count = 10)
    {
        $id = intval($id);
        $count = intval($count);
        $excludeCondition = '';
        if (!empty($exclude)) {
            foreach ($exclude as $key => $value) {
                $excludeCondition .= ' AND t3.id<>' . intval($value) . ' ';
            }
        }
        $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
          'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id AND t4.id="' . $id . '"' .
          $excludeCondition .
          ') ' .
          'GROUP BY t3.id ' .
          'ORDER BY t3.viewed DESC ' .
          'LIMIT ' . $count;
        return $this->getList($query);
    }

    public function getAlbumCount()
    {
        $query = 'SELECT count(id) as albumcount FROM albums WHERE announced=0';
        $result = $this->_db->fetchAll($query);
        return (int)$result[0]['albumcount'];
    }

    public function getAnnouncedCount()
    {
        // Announcements still ahead; one whose date passed unconfirmed is no news (#54).
        $query = 'SELECT count(id) as albumcount FROM albums WHERE announced=1 AND year>=CURDATE()';
        $result = $this->_db->fetchAll($query);
        return (int)$result[0]['albumcount'];
    }

    public function getFeaturingByArtist($id, $limit = 10)
    {
        $id = intval($id);
        $limit = intval($limit);
        $query = 'SELECT DISTINCT(a1.id) as alb_id
              FROM albums a1, songs a2, feature_lookup a3, album_lookup a4
              WHERE (a3.artistid=' . $id . ' AND a3.songid=a2.id AND a2.id=a4.songid AND a1.id=a4.albumid)';
        $albumIds = $this->_db->fetchAll($query);
        if (empty($albumIds)) {
            return false;
        }

        $condition = array();

        foreach ($albumIds as $key => $value) {
            $condition[] = 't3.albumid=' . $value['alb_id'];
        }

        $query = 'SELECT *, t1.id AS alb_id, t2.id AS art_id
              FROM albums t1, artists t2, album_artist_lookup t3
              WHERE (t2.id=t3.artistid AND t1.id=t3.albumid AND (
              ' . implode(' OR ', $condition) . ')
              ) GROUP BY t1.id' .
                  (($limit != null) ? ' LIMIT ' . $limit : '');

        return $this->getList($query);
    }

    public function getMusicByArtist($id, $limit = 10)
    {
        $id = intval($id);
        $limit = intval($limit);
        $query = 'SELECT DISTINCT(a1.id) as alb_id
              FROM albums a1, songs a2, music_lookup a3, album_lookup a4
              WHERE (a3.artistid=' . $id . ' AND a3.songid=a2.id AND a2.id=a4.songid AND a1.id=a4.albumid)';
        $albumIds = $this->_db->fetchAll($query);
        if (empty($albumIds)) {
            return false;
        }

        $condition = array();

        foreach ($albumIds as $key => $value) {
            $condition[] = 't3.albumid=' . $value['alb_id'];
        }

        $query = 'SELECT *, t1.id AS alb_id, t2.id AS art_id
              FROM albums t1, artists t2, album_artist_lookup t3
              WHERE (t2.id=t3.artistid AND t1.id=t3.albumid AND (
              ' . implode(' OR ', $condition) . ')
              ) GROUP BY t1.id' .
                  (($limit != null) ? ' LIMIT ' . $limit : '');

        return $this->getList($query);
    }

    public function getScratchByArtist($id, $limit = 10)
    {
        $id = intval($id);
        $limit = intval($limit);
        $query = 'SELECT DISTINCT(a1.id) as alb_id
              FROM albums a1, songs a2, scratch_lookup a3, album_lookup a4
              WHERE (a3.artistid=' . $id . ' AND a3.songid=a2.id AND a2.id=a4.songid AND a1.id=a4.albumid)';
        $albumIds = $this->_db->fetchAll($query);
        if (empty($albumIds)) {
            return false;
        }

        $condition = array();

        foreach ($albumIds as $key => $value) {
            $condition[] = 't3.albumid=' . $value['alb_id'];
        }

        $query = 'SELECT *, t1.id AS alb_id, t2.id AS art_id
              FROM albums t1, artists t2, album_artist_lookup t3
              WHERE (t2.id=t3.artistid AND t1.id=t3.albumid AND (
              ' . implode(' OR ', $condition) . ')
              ) GROUP BY t1.id' .
                  (($limit != null) ? ' LIMIT ' . $limit : '');

        return $this->getList($query);
    }

    // List of albums that feature a song with given ID
    public function getSongAlbums($id, $limit)
    {
        $id = intval($id);
        $limit = intval($limit);
        $query = "SELECT *, t1.id as alb_id, t3.id as art_id  
              FROM albums t1, album_lookup t2, artists t3, album_artist_lookup t4 
              WHERE (t1.id=t2.albumid AND t2.songid='$id' AND t4.albumid=t1.id AND t4.artistid=t3.id)
              GROUP BY t1.id" .
                  (($limit != null) ? ' LIMIT ' . $limit : '');
        return $this->getList($query);
    }

    public function getLabelReleases($id, $limit = 10)
    {
        $id = intval($id);

        $query = "SELECT *, t1.id AS alb_id, t3.id AS art_id
    FROM albums t1, `album_artist_lookup` t2, `artists` t3
    WHERE (t3.`id`=t2.`artistid` AND t1.`id`=t2.`albumid` AND t1.`labelid`=$id)
    GROUP BY t1.`id`
    ORDER BY t1.`year` DESC" .
        (($limit != null) ? ' LIMIT ' . $limit : '');
        return $this->getList($query);
    }

    public function getMain()
    {
        // get artist for the homesite Top Story
    }

    public function updateView($id)
    {
        $id = intval($id);
        $query = 'UPDATE albums SET viewed=viewed+1 WHERE id=' . $id;
        $this->_db->query($query);
    }

    public function getSitemap()
    {
        $query = "SELECT t1.id AS alb_id, t1.title AS alb_title
              FROM albums t1
              ORDER BY t1.year DESC";
        return $this->getList($query);
    }

    /**
     * Function returns list of all albums sorted by added date descending, for sitemap-albums.xml
     *
     * @return JkL_List of Model_Alubm_Container
     * @author Kuba
     **/
    public function getAlbumsSitemap($limit = 10000)
    {
        $limit = intval($limit);
        $query = 'SELECT *, t3.id as alb_id, t1.id as art_id, t4.id as lab_id, t3.added as alb_added, t3.addedby as alb_addedby, t3.viewed as alb_viewed ' .
          'FROM artists AS t1, album_artist_lookup AS t2, albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t1.id=t2.artistid AND t2.albumid=t3.id) ' .
          'GROUP BY t3.id ' .
          'ORDER BY t3.added DESC ' .
          'LIMIT ' . $limit;
        return $this->getList($query);
    }
}
