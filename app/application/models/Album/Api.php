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
     * What a list needs of an album: its own row, under the aliases the container reads. The
     * credits come from album_artist_lookup separately (_withCredits), so a list reads albums
     * alone, with no join to artists to group back into one row per album: grouping a join
     * takes a temporary table, and SELECT * across albums, artists and labels carried their
     * TEXT columns into it, which sent it to disk (#69).
     */
    private const LIST_COLUMNS = 't3.*, t3.id AS alb_id, t4.id AS lab_id, t3.added AS alb_added, t3.addedby AS alb_addedby, t3.viewed AS alb_viewed';

    /** The same for the lists that never named an album's label, so no label is looked up for each */
    private const BARE_COLUMNS = 't3.*, t3.id AS alb_id';

    /** The status of an album the site shows; any other keeps it for admins (#168) */
    public const PUBLISHED = 999;

    /** An album with a page has an artist, as the join to artists made sure before */
    private const HAS_ARTIST = 'EXISTS (SELECT 1 FROM album_artist_lookup c JOIN artists a ON a.id = c.artistid WHERE c.albumid = t3.id)';

    /** An album a visitor is shown: published (#168), with a page */
    private const SHOWN = 't3.status = ' . self::PUBLISHED . ' AND ' . self::HAS_ARTIST;

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
    public function getList($query, array $bind = array())
    {
        $albums = new Jkl_List();
        foreach ($this->_withCredits($this->_db->fetchAll($query, $bind)) as $params) {
            $albums->add(new Model_Album_Container($params));
        }
        return $albums;
    }

    /**
     * The list query for exactly these albums, in this order (#69).
     *
     * @param array $ids
     */
    private function _byIds(array $ids)
    {
        $ids = array_map('intval', $ids);
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid';
        if (empty($ids)) {
            return $query . ' WHERE 1 = 0';
        }
        $list = implode(', ', $ids);
        return $query . ' WHERE t3.id IN (' . $list . ') ORDER BY ' . Jkl_Db::inOrder('t3.id', $ids);
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

    /**
     * Whether album $id has a page: it is there, and credits an artist (#147). An unpublished
     * one has a page too, which only an admin is let into (#168); isPublished() says which.
     */
    public function exists($id)
    {
        return !empty($this->_db->fetchAll('SELECT 1 FROM albums AS t3 WHERE t3.id = ? AND ' . self::HAS_ARTIST, array((int) $id)));
    }

    /** Whether a visitor may see album $id (#168) */
    public function isPublished($id)
    {
        return !empty($this->_db->fetchAll('SELECT 1 FROM albums WHERE id = ? AND status = ?', array((int) $id, self::PUBLISHED)));
    }

    /** The parts an imported album needs to be published (#168), as an admin reads them */
    public const PARTS = array('label' => 'wytwórnia', 'date' => 'data dzienna', 'tracklist' => 'tracklista');

    /**
     * What an album lacks to be published (#168): a label, or being a self-release; a release
     * date to the day; a tracklist, its lengths aside. Nothing for one that has all three.
     *
     * @param int|null    $labelId
     * @param bool        $selfReleased
     * @param string|null $date      albums.year
     * @param string|null $precision albums.release_date_precision
     * @param int         $tracks
     * @return string[] what it lacks, as PARTS names it, in that order
     */
    public static function lacks($labelId, $selfReleased, $date, $precision, $tracks)
    {
        $lacks = array();
        if (empty($labelId) && empty($selfReleased)) {
            $lacks[] = self::PARTS['label'];
        }
        if (empty($date) || 'day' !== $precision) {
            $lacks[] = self::PARTS['date'];
        }
        if ((int) $tracks < 1) {
            $lacks[] = self::PARTS['tracklist'];
        }
        return $lacks;
    }

    /** What album $id lacks to be published, as the catalogue has it now */
    public function lacksOf($id)
    {
        $rows = $this->_db->fetchAll(
            'SELECT labelid, self_released, year, release_date_precision,
                    (SELECT COUNT(*) FROM album_lookup l WHERE l.albumid = a.id) AS tracks
               FROM albums a WHERE a.id = ?',
            array((int) $id)
        );
        if (empty($rows)) {
            throw new RuntimeException(sprintf('No album %d', $id));
        }
        $row = $rows[0];
        return self::lacks($row['labelid'], (int) $row['self_released'], $row['year'], $row['release_date_precision'], $row['tracks']);
    }

    public function find($id, $full = false)
    {
        $id = intval($id);
        $query = "SELECT *, t1.id as alb_id, t1.labelid AS lab_id, t1.epfor as epforid, t1.added as alb_added, t1.addedby as alb_addedby, t1.viewed as alb_viewed, t3.id as art_id " .
        "FROM albums t1, album_artist_lookup t2, artists t3 " .
        "WHERE (t3.id=t2.artistid AND t2.albumid=t1.id AND t1.id=" . $id . ") " .
        // The album's artist is its credit with the lowest artist id, as MariaDB read them by
        // the (albumid, artistid) key; its name is in the album's address, so it must not move.
        "ORDER BY t3.id";
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
            if (!empty($params['epforid']) && $this->isPublished($params['epforid'])) {
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
        $query = 'SELECT id FROM albums WHERE epfor=' . $id . ' AND status = ' . self::PUBLISHED . ' ORDER BY year IS NULL, year DESC';
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
        $limit = intval($limit);
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE ' . $this->_db->searchLike('t3.title') . ' AND ' . self::SHOWN . ' ' .
          'ORDER BY t3.viewed DESC' .
          (($limit != null) ? ' LIMIT ' . $limit : '') .
          ' OFFSET ' . ($page * $limit);
        return $this->getList($query, array(Jkl_Db::contains($like)));
    }

    public function getLikeCount($like = '')
    {
        $query = "SELECT count(*) as count
              FROM albums AS t1
              WHERE t1.status = " . self::PUBLISHED . " AND " . $this->_db->searchLike('t1.title');
        $result = $this->_db->fetchAll($query, array(Jkl_Db::contains($like)));
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
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE ' . self::SHOWN . ' ' .
          'ORDER BY t3.viewed DESC ' .
          'LIMIT ' . $count;
        return $this->getList($query);
    }

    /**
     * The albums most viewed since $since (#194), filled up from the most viewed of all time when
     * the period has fewer, so a list is never empty, as right after the counting began (#68);
     * all time when $since is null.
     *
     * @return array the list (Jkl_List), and how many of it the period itself ranked
     */
    public function popularSince($since, $count = 20)
    {
        $count = (int) $count;
        $ids = null === $since ? array() : Model_PageView_Api::getInstance()->mostViewedSince('album', $since, $count, self::SHOWN);
        $counted = count($ids);
        if ($counted < $count) {
            $rows = $this->_db->fetchAll(
                'SELECT t3.id FROM albums t3 WHERE ' . self::SHOWN . (empty($ids) ? '' : ' AND t3.id NOT IN (' . implode(', ', $ids) . ')') .
                ' ORDER BY t3.viewed DESC, t3.id LIMIT ' . ($count - $counted)
            );
            $ids = array_merge($ids, array_map('intval', array_column($rows, 'id')));
        }
        return array($this->getList($this->_byIds($ids)), $counted);
    }

    /**
     * The years with published albums (#196), the latest first
     *
     * @return array of array('year' => int, 'albums' => int)
     */
    public function getYears()
    {
        $rows = $this->_db->fetchAll(
            'SELECT EXTRACT(YEAR FROM t3.year) AS y, COUNT(*) AS n FROM albums t3
              WHERE t3.year IS NOT NULL AND ' . self::SHOWN . '
              GROUP BY y ORDER BY y DESC'
        );
        return array_map(function ($row) {
            return array('year' => (int) $row['y'], 'albums' => (int) $row['n']);
        }, $rows);
    }

    /**
     * A year's published albums (#196): by date, those with a day first, then those with a
     * month, then those with the year alone
     */
    public function getByYear($year)
    {
        $year = (int) $year;
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          "WHERE t3.year >= '" . $year . "-01-01' AND t3.year < '" . ($year + 1) . "-01-01' AND " . self::SHOWN . ' ' .
          "ORDER BY CASE t3.release_date_precision WHEN 'day' THEN 0 WHEN 'month' THEN 1 ELSE 2 END, t3.year, t3.title, t3.id";
        return $this->getList($query);
    }

    /**
     * The published albums whose premiere was on this day and month in an earlier year (#197),
     * dated to the day, the oldest anniversary first
     *
     * @param string $today Y-m-d, Warsaw's
     */
    public function getOnThisDay($today, $count = 6)
    {
        list($year, $month, $day) = array_map('intval', explode('-', $today));
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          "WHERE t3.release_date_precision = 'day' AND EXTRACT(MONTH FROM t3.year) = " . $month . ' AND EXTRACT(DAY FROM t3.year) = ' . $day .
          ' AND EXTRACT(YEAR FROM t3.year) < ' . $year . ' AND ' . self::SHOWN . ' ' .
          'ORDER BY t3.year, t3.id LIMIT ' . (int) $count;
        return $this->getList($query);
    }

    /** A year's most viewed albums, of all time's views (#196) */
    public function getPopularOfYear($year, $count = 10)
    {
        $year = (int) $year;
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          "WHERE t3.year >= '" . $year . "-01-01' AND t3.year < '" . ($year + 1) . "-01-01' AND " . self::SHOWN . ' ' .
          'ORDER BY t3.viewed DESC, t3.id LIMIT ' . (int) $count;
        return $this->getList($query);
    }

    public function getBest($count = 10)
    {
        $count = intval($count);
        $query = 'SELECT ' . self::LIST_COLUMNS . ', t2.rating AS rating ' .
          'FROM albums AS t3 JOIN ratings_avg AS t2 ON t2.albumid = t3.id LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE ' . self::SHOWN . ' ' .
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
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE t3.announced=0 AND ' . self::SHOWN . ' ' .
          'ORDER BY t3.year IS NULL, t3.year DESC ' .
          'LIMIT ' . $count . ' ' .
          'OFFSET ' . ($page * $count);
        return $this->getList($query);
    }

    /**
     * The published albums the catalogue gained last, the newest first (#242): what the home page
     * opens with, so a visitor, and AdSense, sees the site is kept
     */
    public function getRecentlyAdded($count = 8)
    {
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE ' . self::SHOWN . ' ' .
          'ORDER BY t3.added IS NULL, t3.added DESC, t3.id DESC ' .
          'LIMIT ' . (int) $count;
        return $this->getList($query);
    }

    public function getAnnounced($count = 20, $page = 1)
    {
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE t3.announced=1 AND t3.year>=CURRENT_DATE AND ' . self::SHOWN . ' ' .
          'ORDER BY t3.year ASC ' .
          'LIMIT ' . $count . ' ' .
          'OFFSET ' . ($page * $count);
        return $this->getList($query);
    }

    public function getFirstLetters()
    {
        // Each letter once whatever its case, as MariaDB's collation had it on its own (#232)
        $query = 'SELECT DISTINCT UPPER(SUBSTR(title, 1, 1)) as name from albums WHERE status = ' . self::PUBLISHED . ' order by name ASC';
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
        // The ids first: sorting the join of credits and albums takes a temporary table, which
        // stays in memory with ids alone, then the rows by id (#69). One credit per album and
        // artist (0011), so the join gives each album once.
        $ids = array_column($this->_db->fetchAll(
            'SELECT t3.id FROM album_artist_lookup AS t2 JOIN albums AS t3 ON t3.id=t2.albumid ' .
            'WHERE (t2.artistid=' . $id . ' AND t3.status = ' . self::PUBLISHED .
            $excludeCondition .
            ') ' .
            'ORDER BY t3.' . $order . ' IS NULL, t3.' . $order . ' DESC ' .
            (($count) ? 'LIMIT ' . $count : '')
        ), 'id');
        return self::getList($this->_byIds($ids));
    }

    public function getArtistsAlbumsCount($id)
    {
        $id = intval($id);
        $query = "SELECT count(*) as count
              FROM albums t1, album_artist_lookup t2, band_lookup t3
              WHERE (t3.bandid=t2.artistid AND t3.artistid=$id AND t1.id=t2.albumid AND t1.status=" . self::PUBLISHED . ")";
        $result = $this->_db->fetchAll($query);
        $projectAlbums = $result[0]['count'];

        $query = "SELECT count(*) as count
              FROM albums t1, album_artist_lookup t2
              WHERE (t1.id=t2.albumid AND t2.artistid=$id AND t1.status=" . self::PUBLISHED . ");";
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
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE (t3.labelid=' . $id . ' AND t4.id IS NOT NULL AND ' . self::SHOWN .
          $excludeCondition .
          ') ' .
          'ORDER BY t3.viewed DESC ' .
          'LIMIT ' . $count;
        return $this->getList($query);
    }

    public function getAlbumCount()
    {
        $query = 'SELECT count(id) as albumcount FROM albums WHERE announced=0 AND status = ' . self::PUBLISHED;
        $result = $this->_db->fetchAll($query);
        return (int)$result[0]['albumcount'];
    }

    public function getAnnouncedCount()
    {
        // Announcements still ahead; one whose date passed unconfirmed is no news (#54).
        $query = 'SELECT count(id) as albumcount FROM albums WHERE announced=1 AND year>=CURRENT_DATE AND status = ' . self::PUBLISHED;
        $result = $this->_db->fetchAll($query);
        return (int)$result[0]['albumcount'];
    }

    /**
     * The released albums a visitor can open, and the first and last year they came out in, for
     * the about page's figures (#218): array(albums, from, to), the years null for none
     */
    public function getShownFigures()
    {
        $rows = $this->_db->fetchAll(
            'SELECT COUNT(*) AS n, MIN(EXTRACT(YEAR FROM t3.year)) AS first, MAX(EXTRACT(YEAR FROM t3.year)) AS last FROM albums t3
              WHERE t3.announced = 0 AND ' . self::SHOWN
        );
        return array(
            (int) $rows[0]['n'],
            null === $rows[0]['first'] ? null : (int) $rows[0]['first'],
            null === $rows[0]['last'] ? null : (int) $rows[0]['last'],
        );
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

        $albumIds = array_map('intval', array_column($albumIds, 'alb_id'));

        $query = 'SELECT ' . self::BARE_COLUMNS . ' FROM albums AS t3
              WHERE t3.id IN (' . implode(', ', $albumIds) . ') AND ' . self::SHOWN . ' ORDER BY t3.id' .
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

        $albumIds = array_map('intval', array_column($albumIds, 'alb_id'));

        $query = 'SELECT ' . self::BARE_COLUMNS . ' FROM albums AS t3
              WHERE t3.id IN (' . implode(', ', $albumIds) . ') AND ' . self::SHOWN . ' ORDER BY t3.id' .
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

        $albumIds = array_map('intval', array_column($albumIds, 'alb_id'));

        $query = 'SELECT ' . self::BARE_COLUMNS . ' FROM albums AS t3
              WHERE t3.id IN (' . implode(', ', $albumIds) . ') AND ' . self::SHOWN . ' ORDER BY t3.id' .
                  (($limit != null) ? ' LIMIT ' . $limit : '');

        return $this->getList($query);
    }

    // List of albums that feature a song with given ID
    public function getSongAlbums($id, $limit)
    {
        $id = intval($id);
        $limit = intval($limit);
        // A song on an album twice is one album; IN gives each once, without grouping.
        $query = 'SELECT ' . self::BARE_COLUMNS . ' FROM albums AS t3
              WHERE t3.id IN (SELECT albumid FROM album_lookup WHERE songid=' . $id . ') AND ' . self::SHOWN . ' ORDER BY t3.id' .
                  (($limit != null) ? ' LIMIT ' . $limit : '');
        return $this->getList($query);
    }

    public function getLabelReleases($id, $limit = 10)
    {
        $id = intval($id);

        $query = 'SELECT ' . self::BARE_COLUMNS . ' FROM albums AS t3
    WHERE t3.labelid=' . $id . ' AND ' . self::SHOWN . '
    ORDER BY t3.year IS NULL, t3.year DESC' .
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
              WHERE t1.status = " . self::PUBLISHED . "
              ORDER BY t1.year IS NULL, t1.year DESC";
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
        $query = 'SELECT ' . self::LIST_COLUMNS . ' FROM albums AS t3 LEFT JOIN labels AS t4 ON t4.id=t3.labelid ' .
          'WHERE ' . self::SHOWN . ' ' .
          'ORDER BY t3.added DESC ' .
          'LIMIT ' . $limit;
        return $this->getList($query);
    }
}
