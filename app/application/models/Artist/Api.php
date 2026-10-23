<?php

/**
 * Artist Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/

class Model_Artist_Api extends Jkl_Model_Api
{
    /**
     * An artist whose page has something of its own (#243): a published album, a credit on a
     * song a visitor is shown, a group it belongs to or members, or a profile. A page with none
     * of them asks search engines not to index it and stays out of the sitemap and the lists of
     * artists added; the condition is on the artists table aliased t1, for all three. A credit
     * on a song only unpublished albums hold is no content, as the page lists no such song
     * (#276): an import credits names, placeholders among them, on albums the publication rule
     * keeps back. An artist an import made, waiting in the release queue with its album (queued,
     * #258), has nothing of its own until a published album credits it. The four kinds of credit
     * are one EXISTS over their union: four, each with SHOWN, raised the planner's cost of the
     * sitemap's whole-table scan past PostgreSQL's threshold for optimising JIT, which spent half
     * a second compiling a query that runs in 35 ms.
     */
    public const HAS_CONTENT = "(EXISTS (SELECT 1 FROM album_artist_lookup c JOIN albums a ON a.id = c.albumid WHERE c.artistid = t1.id AND a.status = 999)
        OR (t1.queued IS NULL AND (t1.profile <> ''
            OR EXISTS (SELECT 1 FROM (SELECT artistid, songid FROM artist_lookup UNION ALL SELECT artistid, songid FROM music_lookup
                    UNION ALL SELECT artistid, songid FROM feature_lookup UNION ALL SELECT artistid, songid FROM scratch_lookup) k
                WHERE k.artistid = t1.id AND k.songid " . Model_Song_Api::SHOWN . ")
            OR EXISTS (SELECT 1 FROM band_lookup b WHERE b.artistid = t1.id OR b.bandid = t1.id))))";

    private static $_instance;

    /**
     * What a credit on a song needs of an artist. These lists sort a join, which takes a
     * temporary table, and one holding TEXT columns, as SELECT * would, goes to disk (#69).
     */
    private const CREDIT_COLUMNS = 't1.id AS art_id, t1.name, t1.disambiguation';

    /**
     * Singleton instance
     *
     * @return Model_Artist_Api
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
        $result = $this->_db->fetchAll($query, $bind);
        $artists = new Jkl_List();

        foreach ($result as $params) {
            $artists->add(new Model_Artist_Container($params));
        }
        return Model_Artist_Container::qualifyNamesakes($artists);
    }

    public function getFirstLetters()
    {
        // Each letter once whatever its case (#232)
        $query = 'SELECT DISTINCT UPPER(SUBSTR(name, 1, 1)) as name from artists order by name ASC';
        return  $this->_db->fetchAll($query);
    }

    public function getLikeLetter($like = '', $limit = 15, $page = 1)
    {
        $limit = intval($limit);
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;

        $query = "SELECT *, t1.id as art_id
              FROM artists AS t1
              WHERE (t1.name LIKE ?)
              ORDER BY t1.viewed DESC" .
                  (($limit != null) ? ' LIMIT ' . $limit . ' OFFSET ' . ($page * $limit) : '');

        $result = $this->_db->fetchAll($query, array(Jkl_Db::startsWith($like)));
        return $this->_listed($result);
    }

    public function getLike($like = '', $limit = 15, $page = 1)
    {
        $limit = intval($limit);
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;

        $query = "SELECT *, t1.id as art_id
              FROM artists AS t1
              WHERE (" . $this->_db->searchLike('t1.name') . ")
              ORDER BY t1.viewed DESC" .
                  (($limit != null) ? ' LIMIT ' . $limit . ' OFFSET ' . ($page * $limit) : '');

        $result = $this->_db->fetchAll($query, array(Jkl_Db::contains($like)));
        return $this->_listed($result);
    }

    public function getNicknamesLike($like = '', $limit = 15, $page = 1)
    {
        $limit = intval($limit);
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;

        $query = "SELECT *, t1.id AS art_id 
              FROM artists t1, altnames_lookup t2
              WHERE (t1.id=t2.artistid AND " . $this->_db->searchLike('t2.altname') . ")" .
                  "ORDER BY t1.viewed DESC" .
                  (($limit != null) ? ' LIMIT ' . $limit : '') .
                  ' OFFSET ' . ($page * $limit);

        $result = $this->_db->fetchAll($query, array(Jkl_Db::contains($like)));
        return $this->_listed($result);
    }

    public function getMostPopular($limit = 40)
    {
        $limit = intval($limit);
        $query = 'SELECT *, t1.id as art_id, ' .
          '(SELECT COUNT(*) FROM hhb_comments WHERE com_object_id = t1.id AND com_object_type = \'p\') as comment_count ' .
          'FROM artists AS t1 ' .
          'ORDER BY t1.viewed DESC ' .
          (($limit) ? 'LIMIT ' . $limit : '');
        return $this->_listed($this->_db->fetchAll($query), true);
    }

    /**
     * The artists most viewed since $since (#194), filled up from the most viewed of all time
     * when the period has fewer; all time when $since is null.
     *
     * @return array the list (Jkl_List), and how many of it the period itself ranked
     */
    public function popularSince($since, $count = 20)
    {
        $count = (int) $count;
        $ids = null === $since ? array() : Model_PageView_Api::getInstance()->mostViewedSince('artist', $since, $count);
        $counted = count($ids);
        if ($counted < $count) {
            $rows = $this->_db->fetchAll(
                'SELECT id FROM artists' . (empty($ids) ? '' : ' WHERE id NOT IN (' . implode(', ', $ids) . ')') .
                ' ORDER BY viewed DESC, id LIMIT ' . ($count - $counted)
            );
            $ids = array_merge($ids, array_map('intval', array_column($rows, 'id')));
        }
        if (empty($ids)) {
            return array(new Jkl_List(), 0);
        }
        $rows = $this->_db->fetchAll(
            'SELECT *, t1.id as art_id, ' .
            '(SELECT COUNT(*) FROM hhb_comments WHERE com_object_id = t1.id AND com_object_type = \'p\') as comment_count ' .
            'FROM artists AS t1 WHERE t1.id IN (' . implode(', ', $ids) . ') ORDER BY ' . Jkl_Db::inOrder('t1.id', $ids)
        );
        return array($this->_listed($rows, true), $counted);
    }

    /**
     * Artists' rows as a list shows them: with their other names and album count, and as a
     * ranking ($photo) with their main photo, a query each for the whole list rather than for
     * each artist (#255)
     */
    private function _listed(array $result, $photo = false)
    {
        $ids = array_column($result, 'art_id');
        $aka = $this->_getAkas($ids);
        $counts = Model_Album_Api::getInstance()->getArtistsAlbumsCounts($ids);
        $photos = $photo ? Model_Image_Api::getInstance()->getMainPhotos($ids) : array();
        $artists = new Jkl_List();

        foreach ($result as $params) {
            $id = (int) $params['art_id'];
            $params['aka'] = $aka[$id];
            $params['album_count'] = $counts[$id];
            if ($photo) {
                $params['photo'] = $photos[$id];
            }
            $artists->add(new Model_Artist_Container($params));
        }

        return Model_Artist_Container::qualifyNamesakes($artists);
    }

    public function getNewest($limit = 20)
    {
        $limit = intval($limit);
        $query = 'SELECT * ' .
        'FROM artists ' .
        'ORDER by added DESC ' .
        'LIMIT ' . $limit;

        return $this->getList($query);
    }

    /**
     * An artist by id. The row is read once a request (#255): a page credits the same artist on
     * many albums and songs. A $full one, for the artist's own page, is read every time.
     */
    public function find($id, $full = false)
    {
        $id = intval($id);
        $params = $full ? null : $this->remembered($id);
        if (null === $params) {
            $result = $this->_db->fetchAll('select *, id as art_id from artists where id=' . $id);
            if (empty($result)) {
                return null;
            }
            $params = $result[0];
            $this->remember($id, $params);
        }

        if ($full) {
            // photos
            $params['photos'] = Model_Image_Api::getInstance()->getArtistPhotos($id);

            // also known as
            $params['aka'] = $this->_getAkas(array($id))[$id];

            // band members
            $params['members'] = $this->_getMembers($id);

            // member of bands
            $params['projects'] = $this->_getProjects($id);

            // city
            $params['cities'] = Model_City_Api::getInstance()->getArtistCities($id);
        } // full

        $item = new Model_Artist_Container($params, $full);
        return $item;
    }

    /**
     * Artists' rows a query read along with its own, kept for find() (#255): what a container
     * that is not $full reads, art_id, name, disambiguation, since, till and viewed, is enough.
     */
    public function rememberRows(array $rows)
    {
        foreach ($rows as $row) {
            if (null === $this->remembered($row['art_id'])) {
                $this->remember($row['art_id'], $row);
            }
        }
    }

    private function _getMembers($id)
    {
        $id = intval($id);
        $query = 'SELECT t1.name, t1.disambiguation, t1.id as art_id, t2.insince, t2.awaysince FROM artists AS t1, band_lookup AS t2 ' .
                  'WHERE (t1.id=t2.artistid AND t2.bandid=' . $id . ') ORDER BY t1.name';
        return $this->getList($query);
    }

    private function _getProjects($id)
    {
        $id = intval($id);
        $query = 'SELECT t1.name, t1.disambiguation, t1.id as art_id, t2.insince AS since, t2.awaysince AS till FROM artists AS t1, band_lookup AS t2 ' .
                  'WHERE (t1.id=t2.bandid AND t2.artistid=' . $id . ') ORDER BY t1.name';
        return $this->getList($query);
    }

    /**
     * The other names of each of these artists, in one query for a whole list (#255)
     *
     * @param int[] $ids
     * @return array artist id => Jkl_List, empty for an artist with none
     */
    private function _getAkas(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $aka = array();
        foreach ($ids as $id) {
            $aka[$id] = new Jkl_List('Also known as list');
        }
        if (!empty($ids)) {
            $query = 'SELECT t1.artistid, t1.altname FROM altnames_lookup AS t1 ' .
                      'WHERE t1.artistid IN (' . implode(', ', $ids) . ') ORDER BY t1.artistid, t1.altname';
            foreach ($this->_db->fetchAll($query) as $value) {
                $aka[(int) $value['artistid']]->add($value['altname']);
            }
        }
        return $aka;
    }

    /**
     * Who is credited on each of these songs, in one query for a whole tracklist rather than
     * four for each song (#255): its guests with their role, who made its music, who scratched
     * on it, and its own artists, each list by name.
     *
     * @param int[] $songIds
     * @return array song id => array('featuring', 'music', 'scratch', 'artist'), each a Jkl_List
     */
    public function getSongsCredits(array $songIds)
    {
        $songIds = array_values(array_unique(array_map('intval', $songIds)));
        $credits = array();
        foreach ($songIds as $songId) {
            $credits[$songId] = array('featuring' => new Jkl_List(), 'music' => new Jkl_List(), 'scratch' => new Jkl_List(), 'artist' => new Jkl_List());
        }
        if (empty($songIds)) {
            return $credits;
        }
        $in = implode(', ', $songIds);
        $lookup = function ($credit, $table) use ($in) {
            return "SELECT '" . $credit . "', t2.songid, " . self::CREDIT_COLUMNS . ', NULL, NULL, NULL, NULL, NULL '
                . 'FROM artists t1 JOIN ' . $table . ' t2 ON t2.artistid = t1.id WHERE t2.songid IN (' . $in . ')';
        };
        // A guest's row brings what find() reads of an artist, for find() to keep. Sorting needs
        // a temporary table, which goes to disk when it holds a TEXT column (#69): the role's
        // name is cut to the length a role has. A guest in several roles has them in the order
        // of the roles' ids, the order the song's page has always shown them in.
        $rows = $this->_db->fetchAll(
            "SELECT 'featuring' AS credit, t2.songid, t1.id AS art_id, t1.name, t1.disambiguation, t1.since, t1.till, t1.viewed, LEFT(t3.feattype, 64) AS feattype, t2.feattype AS role "
            . 'FROM artists t1 JOIN feature_lookup t2 ON t2.artistid = t1.id JOIN feattypes t3 ON t3.id = t2.feattype WHERE t2.songid IN (' . $in . ')'
            . ' UNION ALL ' . $lookup('music', 'music_lookup')
            . ' UNION ALL ' . $lookup('scratch', 'scratch_lookup')
            . ' UNION ALL ' . $lookup('artist', 'artist_lookup')
            . ' ORDER BY credit, songid, name, art_id, role'
        );
        $guests = array();
        foreach ($rows as $row) {
            if ('featuring' === $row['credit']) {
                $guests[] = array_intersect_key($row, array_flip(array('art_id', 'name', 'disambiguation', 'since', 'till', 'viewed')));
            }
        }
        $this->rememberRows($guests);
        foreach ($rows as $row) {
            if ('featuring' === $row['credit']) {
                $artist = $this->find($row['art_id']);
                $artist->featType = $row['feattype'];
            } else {
                $artist = new Model_Artist_Container(array('art_id' => $row['art_id'], 'name' => $row['name'], 'disambiguation' => $row['disambiguation']));
            }
            $credits[(int) $row['songid']][$row['credit']]->add($artist);
        }
        foreach ($credits as $lists) {
            array_map(array('Model_Artist_Container', 'qualifyNamesakes'), $lists);
        }
        return $credits;
    }

    public function getWithMostProjectAlbums($limit = 10)
    {
        $limit = intval($limit);
        $query = "SELECT t4.id AS art_id, t4.name, t4.disambiguation, count(*) AS album_count
     FROM albums t1, album_artist_lookup t2, band_lookup t3, artists t4
     WHERE (t3.bandid=t2.artistid AND t3.artistid=t4.id AND t1.id=t2.albumid AND t1.status=" . Model_Album_Api::PUBLISHED . ")
     GROUP BY t4.id, t4.name, t4.disambiguation
     ORDER BY album_count DESC" .
        (($limit != null) ? ' LIMIT ' . $limit : '');

        return $this->getList($query);
    }

    public function getWithMostSoloAlbums($limit = 10)
    {
        $limit = intval($limit);

        $query = "SELECT t3.id AS art_id, t3.name, t3.disambiguation, count(*) AS album_count
     FROM albums t1, album_artist_lookup t2, artists t3
     WHERE (t3.id=t2.artistid AND t1.id=t2.albumid AND t3.id<>190 AND t1.status=" . Model_Album_Api::PUBLISHED . ")
     GROUP BY t3.id, t3.name, t3.disambiguation
     ORDER BY album_count DESC" .
        (($limit != null) ? ' LIMIT ' . $limit : '');

        return $this->getList($query);
    }

    /** Whether an artist's page has something of its own: HAS_CONTENT */
    public function hasContent($id)
    {
        return !empty($this->_db->fetchAll('SELECT 1 FROM artists t1 WHERE t1.id = ? AND ' . self::HAS_CONTENT, array((int) $id)));
    }

    /** The artists the sitemap lists: those whose page has something of its own (#243) */
    public function getSitemap($limit)
    {
        return $this->getList('SELECT t1.id AS art_id, t1.name, t1.disambiguation FROM artists t1
            WHERE ' . self::HAS_CONTENT . ' ORDER BY t1.added DESC LIMIT ' . (int) $limit);
    }

    /** The artists added last that have something to show (HAS_CONTENT), newest first, an unknown date last */
    public function getRecentlyAdded($limit = 10)
    {
        $limit = intval($limit);
        $query = "SELECT t1.id AS art_id, t1.name, t1.disambiguation
    FROM artists t1
    WHERE " . self::HAS_CONTENT . "
    ORDER BY t1.added IS NULL, t1.added DESC, t1.id DESC" .
        (($limit != null) ? ' LIMIT ' . $limit : '');

        return $this->getList($query);
    }

    public function updateView($id)
    {
        $id = intval($id);
        $query = 'UPDATE artists SET viewed=viewed+1 WHERE id=' . $id;
        $this->_db->query($query);
    }

    /** All the artists and groups, each with a page, for the about page's figures (#218) */
    public function getCount()
    {
        $result = $this->_db->fetchAll('SELECT COUNT(*) AS n FROM artists');
        return (int) $result[0]['n'];
    }

    public function getLikeCount($like = '')
    {
        $query = "SELECT count(*) as count
              FROM artists AS t1
              WHERE " . $this->_db->searchLike('t1.name');
        $result = $this->_db->fetchAll($query, array(Jkl_Db::contains($like)));
        return intval($result[0]['count']);
    }
}
