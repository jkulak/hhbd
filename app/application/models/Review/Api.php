<?php

/**
 * The doubts an import leaves for a person, and how an admin settles them (#103). The importer
 * adds items; an admin sees the open ones on the page of the row they are about, and all of
 * them on one list, and settles each with one of the actions its reason allows.
 */
class Model_Review_Api extends Jkl_Model_Api
{
    /** Each reason, as the panel names it, with the actions that settle it */
    public const REASONS = array(
        'namesake'          => array('label' => 'Ta sama nazwa co inny wykonawca', 'actions' => array('merge', 'keep', 'qualifier')),
        'cover_placeholder' => array('label' => 'Okładka zastępcza', 'actions' => array('accept')),
        'date_disputed'     => array('label' => 'Źródła nie zgadzają się co do daty', 'actions' => array('pick')),
        'type_disputed'     => array('label' => 'Źródła nie zgadzają się co do typu wydania', 'actions' => array('pick')),
        'single_source'     => array('label' => 'Znane tylko z jednego źródła', 'actions' => array('accept')),
    );

    /** What each action writes as the item's resolution */
    public const RESOLUTIONS = array(
        'merge' => 'merged', 'keep' => 'kept', 'qualifier' => 'qualified', 'accept' => 'accepted', 'pick' => 'picked',
    );

    /**
     * Every column that holds an artist's id, as table => columns; a merge points them all at
     * the artist it keeps. The schema test checks no artistid, bandid or aid column is missing.
     */
    public const ARTIST_COLUMNS = array(
        'album_artist_lookup'   => array('artistid'),
        'altnames_lookup'       => array('artistid'),
        'artist_city_lookup'    => array('artistid'),
        'artist_concert_lookup' => array('artistid'),
        'artist_lookup'         => array('artistid'),
        'artists_everyweek'     => array('aid'),
        'artists_photos'        => array('artistid'),
        'band_lookup'           => array('artistid', 'bandid'),
        'feature_lookup'        => array('artistid'),
        'music_lookup'          => array('artistid'),
        'news_artist_lookup'    => array('artistid'),
        'remix_lookup'          => array('artistid'),
        'scratch_lookup'        => array('artistid'),
    );

    /** And the tables that name a row by its type and id, as table => (type column, id column, an artist's type) */
    public const ARTIST_ENTITIES = array(
        'external_ids'      => array('entity_type', 'entity_id', 'artist'),
        'import_provenance' => array('entity_type', 'entity_id', 'artist'),
        'hhb_comments'      => array('com_object_type', 'com_object_id', 'p'),
        'review_items'      => array('entity_type', 'entity_id', 'artist'),
    );

    private const RELEASE_TYPES = array('album', 'ep', 'mixtape', 'compilation', 'beat_tape', 'single', 'other');

    private static $_instance;

    /** @return Model_Review_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Opens an item, unless the row has one open for that reason already: the same batch read
     * twice asks once.
     *
     * @return int the item's id
     */
    public function add($entityType, $entityId, $reason, array $detail = array(), $runId = null)
    {
        if (!isset(self::REASONS[$reason])) {
            throw new InvalidArgumentException(sprintf('No review reason "%s"', $reason));
        }
        $open = $this->_db->fetchAll(
            'SELECT id FROM review_items WHERE entity_type = ? AND entity_id = ? AND reason = ? AND resolved IS NULL',
            array($entityType, (int) $entityId, $reason)
        );
        if (!empty($open)) {
            return (int) $open[0]['id'];
        }
        $this->_db->query(
            'INSERT INTO review_items (entity_type, entity_id, reason, detail, run_id) VALUES (?, ?, ?, ?, ?)',
            array($entityType, (int) $entityId, $reason, json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $runId)
        );
        return (int) $this->_db->lastInsertId();
    }

    /** @return Model_Review_Container|null */
    public function find($id)
    {
        $rows = $this->_db->fetchAll('SELECT * FROM review_items WHERE id = ?', array((int) $id));
        return empty($rows) ? null : new Model_Review_Container($rows[0]);
    }

    /** @return Model_Review_Container[] the row's open items, oldest first */
    public function openFor($entityType, $entityId)
    {
        $rows = $this->_db->fetchAll(
            'SELECT * FROM review_items WHERE entity_type = ? AND entity_id = ? AND resolved IS NULL ORDER BY id',
            array($entityType, (int) $entityId)
        );
        return array_map(function ($row) {
            return new Model_Review_Container($row);
        }, $rows);
    }

    /** @return array reason => how many are open, the reasons in REASONS order */
    public function openCounts()
    {
        $counts = array_fill_keys(array_keys(self::REASONS), 0);
        foreach ($this->_db->fetchAll('SELECT reason, COUNT(*) AS n FROM review_items WHERE resolved IS NULL GROUP BY reason') as $row) {
            $counts[$row['reason']] = (int) $row['n'];
        }
        return $counts;
    }

    /** @return Model_Review_Container[] the open items, newest first, of one reason or all */
    public function listOpen($reason = null, $limit = 200)
    {
        $sql = 'SELECT * FROM review_items WHERE resolved IS NULL';
        $values = array();
        if (null !== $reason) {
            $sql .= ' AND reason = ?';
            $values[] = $reason;
        }
        $rows = $this->_db->fetchAll($sql . ' ORDER BY id DESC LIMIT ' . (int) $limit, $values);
        return array_map(function ($row) {
            return new Model_Review_Container($row);
        }, $rows);
    }

    /**
     * Settles an open item with one of the actions its reason allows. Whatever it changes and
     * the item's resolution go in one transaction, and the admin is recorded as updatedby.
     *
     * @param string $value the artist to merge into, the qualifier, or the picked date or type
     */
    public function settle($id, $action, $value, $note, $userId)
    {
        $item = $this->find($id);
        if (null === $item || null !== $item->resolved) {
            throw new RuntimeException('This item is settled already, or there is no such item.');
        }
        if (!in_array($action, self::REASONS[$item->reason]['actions'], true)) {
            throw new RuntimeException(sprintf('"%s" does not settle "%s".', $action, $item->reason));
        }
        $note = '' === trim((string) $note) ? null : trim($note);
        $this->_db->beginTransaction();
        try {
            $undo = null;
            if ('merge' === $action) {
                $undo = $this->mergeArtist($item, (int) $value, $userId);
            } elseif ('qualifier' === $action) {
                $this->setQualifier($item->entityId, $value, $userId);
            } elseif ('accept' === $action && 'cover_placeholder' === $item->reason) {
                $this->_db->query('UPDATE album_covers SET needs_upgrade = 0 WHERE albumid = ?', array($item->entityId));
            } elseif ('pick' === $action && 'date_disputed' === $item->reason) {
                $this->setDate($item->entityId, $value, $userId);
            } elseif ('pick' === $action && 'type_disputed' === $item->reason) {
                $this->setType($item->entityId, $value, $userId);
            }
            $this->close($item->id, self::RESOLUTIONS[$action], $note, $userId, $undo);
            $this->_db->commit();
        } catch (Exception $e) {
            $this->_db->rollBack();
            throw $e;
        }
    }

    /** Settles an item without changing anything else, as the importer does when it replaces a stand-in */
    public function close($id, $resolution, $note, $userId, $undo = null)
    {
        $this->_db->query(
            'UPDATE review_items SET resolved = NOW(), resolved_by = ?, resolution = ?, note = ?, undo_data = ? WHERE id = ? AND resolved IS NULL',
            array((int) $userId, $resolution, $note, null === $undo ? null : json_encode($undo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $id)
        );
    }

    /**
     * Merges the item's artist into another: every reference to it points at the one kept, the
     * duplicate row goes, and its old URL redirects. A reference the kept artist has already
     * (both credited on one song) is dropped instead of doubled. Everything moved or dropped,
     * and the deleted row, is returned for the item's undo_data, so the merge can be taken back.
     *
     * @return array
     */
    private function mergeArtist(Model_Review_Container $item, $intoId, $userId)
    {
        $fromId = $item->entityId;
        if ($intoId === $fromId || !$this->artistExists($intoId)) {
            throw new RuntimeException(sprintf('There is no other artist %d to merge into.', $intoId));
        }
        $artist = $this->_db->fetchAll('SELECT * FROM artists WHERE id = ?', array($fromId));
        if (empty($artist)) {
            throw new RuntimeException(sprintf('Artist %d is gone already.', $fromId));
        }
        $undo = array('artist' => $artist[0], 'into' => $intoId, 'moved' => array(), 'dropped' => array());

        foreach (self::ARTIST_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->moveRows($table, "`$column` = ?", array($fromId), "`$column` = ?", array($intoId), $undo);
            }
        }
        // A member of the band it is merged into would be its own member.
        $self = $this->_db->fetchAll('SELECT * FROM band_lookup WHERE artistid = bandid AND artistid = ?', array($intoId));
        if (!empty($self)) {
            $undo['dropped']['band_lookup'] = array_merge(isset($undo['dropped']['band_lookup']) ? $undo['dropped']['band_lookup'] : array(), $self);
            $this->_db->query('DELETE FROM band_lookup WHERE artistid = bandid AND artistid = ?', array($intoId));
        }
        foreach (self::ARTIST_ENTITIES as $table => list($typeColumn, $idColumn, $type)) {
            $where = "`$typeColumn` = ? AND `$idColumn` = ?";
            if ('review_items' === $table) {
                // This item keeps naming the artist it was about.
                $where .= ' AND id <> ' . (int) $item->id;
            }
            $this->moveRows($table, $where, array($type, $fromId), "`$idColumn` = ?", array($intoId), $undo);
        }

        $this->_db->query('DELETE FROM artists WHERE id = ?', array($fromId));
        $this->_db->query('UPDATE artist_merges SET into_id = ? WHERE into_id = ?', array($intoId, $fromId));
        $this->_db->query(
            'INSERT INTO artist_merges (id, into_id, review_item_id) VALUES (?, ?, ?)',
            array($fromId, $intoId, $item->id)
        );
        $this->touch('artists', $intoId, $userId);
        return $undo;
    }

    /**
     * Points the rows $where finds at the kept artist. Rows a unique key refuses, because the
     * kept artist has the same one, stay behind and are dropped.
     */
    private function moveRows($table, $where, array $whereValues, $set, array $setValues, array &$undo)
    {
        $rows = $this->_db->fetchAll("SELECT * FROM `$table` WHERE $where", $whereValues);
        if (empty($rows)) {
            return;
        }
        $this->_db->query("UPDATE IGNORE `$table` SET $set WHERE $where", array_merge($setValues, $whereValues));
        $left = $this->_db->fetchAll("SELECT * FROM `$table` WHERE $where", $whereValues);
        if (!empty($left)) {
            $this->_db->query("DELETE FROM `$table` WHERE $where", $whereValues);
            $undo['dropped'][$table] = array_merge(isset($undo['dropped'][$table]) ? $undo['dropped'][$table] : array(), $left);
        }
        $undo['moved'][$table] = array_merge(isset($undo['moved'][$table]) ? $undo['moved'][$table] : array(), $rows);
    }

    private function setQualifier($artistId, $qualifier, $userId)
    {
        $qualifier = trim((string) $qualifier);
        if ('' === $qualifier || mb_strlen($qualifier, 'UTF-8') > 190) {
            throw new RuntimeException('A qualifier is 1 to 190 characters.');
        }
        try {
            $this->_db->query('UPDATE artists SET disambiguation = ? WHERE id = ?', array($qualifier, $artistId));
        } catch (Zend_Db_Statement_Exception $e) {
            throw new RuntimeException(sprintf('Another artist of this name has the qualifier "%s" already.', $qualifier));
        }
        $this->touch('artists', $artistId, $userId);
    }

    /** A date as "2016", "2016-11" or "2016-11-03", stored whole with its precision (#54) */
    private function setDate($albumId, $value, $userId)
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4})$/', $value, $m)) {
            list($date, $precision) = array($m[1] . '-01-01', 'year');
        } elseif (preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) {
            list($date, $precision) = array($m[1] . '-' . $m[2] . '-01', 'month');
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            list($date, $precision) = array($value, 'day');
        } else {
            throw new RuntimeException('A date is 2016, 2016-11 or 2016-11-03.');
        }
        if (false === strtotime($date) || date('Y-m-d', strtotime($date)) !== $date) {
            throw new RuntimeException(sprintf('%s is not a date.', $value));
        }
        $this->_db->query(
            'UPDATE albums SET year = ?, release_date_precision = ?, announced = (? > CURDATE()) WHERE id = ?',
            array($date, $precision, $date, $albumId)
        );
        $this->touch('albums', $albumId, $userId);
    }

    private function setType($albumId, $value, $userId)
    {
        if (!in_array($value, self::RELEASE_TYPES, true)) {
            throw new RuntimeException(sprintf('"%s" is not a release type.', $value));
        }
        $this->_db->query('UPDATE albums SET release_type = ? WHERE id = ?', array($value, $albumId));
        $this->touch('albums', $albumId, $userId);
    }

    private function touch($table, $id, $userId)
    {
        $this->_db->query("UPDATE `$table` SET updatedby = ?, updated = NOW() WHERE id = ?", array((int) $userId, (int) $id));
    }

    private function artistExists($id)
    {
        return !empty($this->_db->fetchAll('SELECT id FROM artists WHERE id = ?', array((int) $id)));
    }

    /** The artist a merged one became, following merges of merges; null for one never merged */
    public function mergedInto($artistId)
    {
        $rows = $this->_db->fetchAll('SELECT into_id FROM artist_merges WHERE id = ?', array((int) $artistId));
        return empty($rows) ? null : (int) $rows[0]['into_id'];
    }
}
