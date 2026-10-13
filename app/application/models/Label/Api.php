<?php

/**
 * Label Api
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package hhbd
 **/

class Model_Label_Api extends Jkl_Model_Api
{
    /** Each label's published albums, counted: labelid, album_count */
    private const ALBUM_COUNTS = 'SELECT labelid, COUNT(*) AS album_count FROM albums WHERE status = ' . Model_Album_Api::PUBLISHED . ' GROUP BY labelid';

    private static $_instance;

    /**
     * Singleton instance
     *
     * @return Model_Label_Api
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
        $list = new Jkl_List();
        foreach ($result as $params) {
            $list->add(new Model_Label_Container($params));
        }
        return $list;
    }

    /** Whether there is a label $id, for its page to ask before it builds it (#147) */
    public function exists($id)
    {
        return $this->has('labels', $id);
    }

    /**
     * A label by id, read once a request (#255): a page lists many albums of one label. A $full
     * one, for the label's own page, is read every time.
     */
    public function find($id, $full = false)
    {
        $id = intval($id);
        $row = $full ? null : $this->remembered($id);
        if (null === $row) {
            $result = $this->_db->fetchAll('select *, id AS lab_id from labels where id=' . $id);
            $row = $result[0];
            if (null !== $row) {
                $this->remember($id, $row);
            }
        }
        $item = new Model_Label_Container($row, $full);
        return $item;
    }

    /**
     * Labels' rows a query read along with its own, kept for find() (#255): lab_id, name,
     * website, email, addres, profile, logo, added and updated are what a container reads.
     */
    public function rememberRows(array $rows)
    {
        foreach ($rows as $row) {
            if (null === $this->remembered($row['lab_id'])) {
                $this->remember($row['lab_id'], $row);
            }
        }
    }

    public function getFullList()
    {
        // The albums counted per label alone, then joined: no label's TEXT column goes through
        // the grouping's temporary table (#69), and the grouping is by what it counts (#231).
        $query = "SELECT t1.id AS lab_id, t1.name, t1.website, c.album_count
              FROM labels t1 JOIN (" . self::ALBUM_COUNTS . ") c ON c.labelid = t1.id
              ORDER BY t1.name";
        return $this->getList($query);
    }

    public function getWithMostAlbums($limit = 10)
    {
        $limit = intval($limit);
        $query = "SELECT c.album_count, t1.id AS lab_id, t1.name
              FROM labels t1 JOIN (" . self::ALBUM_COUNTS . ") c ON c.labelid = t1.id
              ORDER BY c.album_count DESC" .
                  (isset($limit) ? " LIMIT $limit" : '');

        return $this->getList($query);
    }

    public function getLike($like, $limit = 10, $page = 1)
    {
        $limit = intval($limit);
        $page = intval($page - 1);
        $page = ($page < 1) ? 0 : $page;

        $query = "SELECT c.album_count, t1.id AS lab_id, t1.name
              FROM labels t1 JOIN (" . self::ALBUM_COUNTS . ") c ON c.labelid = t1.id
              WHERE " . $this->_db->searchLike('t1.name') . "
              ORDER BY t1.viewed DESC" .
                  (($limit != null) ? ' LIMIT ' . $limit : '') .
                  ' OFFSET ' . ($page * $limit);

        return $this->getList($query, array(Jkl_Db::contains($like)));
    }

    /** All the labels, each with a page, for the about page's figures (#218) */
    public function getCount()
    {
        $result = $this->_db->fetchAll('SELECT COUNT(*) AS n FROM labels');
        return (int) $result[0]['n'];
    }

    public function getLikeCount($like = '')
    {
        $query = "SELECT count(*) as count
              FROM labels AS t1
              WHERE " . $this->_db->searchLike('t1.name');
        $result = $this->_db->fetchAll($query, array(Jkl_Db::contains($like)));
        return intval($result[0]['count']);
    }

    public function updateView($id)
    {
        $id = intval($id);
        $query = 'UPDATE labels SET viewed=viewed+1 WHERE id=' . $id;
        $this->_db->query($query);
    }

    /** Every label's name, as an admin picks one (#199) */
    public function getNames()
    {
        return array_column($this->_db->fetchAll('SELECT name FROM labels ORDER BY name'), 'name');
    }

    public function getRecent($limit = 20)
    {
        $limit = intval($limit);
        $query = "SELECT *, t1.id AS lab_id, t1.name AS lab_name
              FROM labels t1
              ORDER BY t1.added DESC" .
                  (($limit != null) ? ' LIMIT ' . $limit : '');
        return $this->getList($query);
    }
}
