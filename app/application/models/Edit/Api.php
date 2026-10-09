<?php

/**
 * An admin's edits to the catalogue, each journalled and each undoable (#115): merging two
 * albums or two artists, deleting an album, an artist or a label, setting one field. The command
 * line (app/tools/edit.php) and the review panel's merge (#103) both come here, so a change
 * leaves the same record whichever way it came.
 *
 * An operation is one row of edit_operations (what, to which rows, who, why, through which
 * path) and one edit_journal row per row it inserted, changed or deleted, with the row before
 * and after. Undo reads an operation's journal backwards and is an operation itself.
 *
 * Nothing here opens a transaction: the caller does, so a dry run is the same code rolled back,
 * and the panel's merge commits with the review item it settles.
 */
class Model_Edit_Api extends Jkl_Model_Api
{
    /** The operations the command line takes, with the arguments each needs */
    public const OPERATIONS = array(
        'merge-albums'  => array('from', 'into'),
        'merge-artists' => array('from', 'into'),
        'delete-album'  => array('id'),
        'delete-artist' => array('id'),
        'delete-label'  => array('id'),
        'set'           => array('table', 'id', 'column', 'value'),
        'undo'          => array('operation'),
    );

    /** The tables whose one field `set` changes */
    public const SETTABLE = array('albums', 'artists', 'labels', 'songs');

    /**
     * Every column that holds an artist's id, as table => columns; a merge points them all at
     * the artist it keeps, a delete removes their rows. tests/review-test.sh checks no artistid,
     * bandid or aid column in the schema is missing.
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

    /** The same for an album: tests/edit-test.sh checks every albumid column is here */
    public const ALBUM_COLUMNS = array(
        'album_artist_lookup' => array('albumid'),
        'album_covers'        => array('albumid'),
        'album_lookup'        => array('albumid'),
        'album_prices'        => array('albumid'),
        'album_ratings'       => array('albumid'),
        'album_reviews'       => array('albumid'),
        'collection'          => array('albumid'),
        'news_album_lookup'   => array('albumid'),
        'ratings'             => array('albumid'),
        'ratings_avg'         => array('albumid'),
        'wishlist'            => array('albumid'),
    );

    /** And for a label: every labelid column */
    public const LABEL_COLUMNS = array(
        'city_label_lookup' => array('labelid'),
        'news_label_lookup' => array('labelid'),
    );

    /** The tables that name a row by type and id, as table => (type column, id column) */
    public const ENTITY_TABLES = array(
        'external_ids'      => array('entity_type', 'entity_id'),
        'import_provenance' => array('entity_type', 'entity_id'),
        'hhb_comments'      => array('com_object_type', 'com_object_id'),
        'review_items'      => array('entity_type', 'entity_id'),
    );

    /** What each of those tables calls an album, an artist and a label */
    private const ENTITY_TYPES = array(
        'album'  => array('external_ids' => 'album', 'import_provenance' => 'album', 'hhb_comments' => 'a', 'review_items' => 'album'),
        'artist' => array('external_ids' => 'artist', 'import_provenance' => 'artist', 'hhb_comments' => 'p', 'review_items' => 'artist'),
        'label'  => array('external_ids' => 'label', 'import_provenance' => 'label', 'hhb_comments' => 'l', 'review_items' => 'label'),
    );

    private static $_instance;

    private $operationId;
    private $userId;
    /** @var string[] one line per row changed, for stdout */
    private $lines = array();
    /** @var array table => the columns that identify one of its rows */
    private $keys = array();

    /** @return Model_Edit_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /** The usr_id of the admin of that name, or null for a name that is no admin's */
    public function adminId($name)
    {
        $rows = $this->_db->fetchAll("SELECT usr_id FROM hhb_users WHERE usr_display_name = ? AND usr_is_admin = 'yes'", array((string) $name));
        return 1 === count($rows) ? (int) $rows[0]['usr_id'] : null;
    }

    /**
     * Runs one operation and journals it, inside the caller's transaction.
     *
     * @param array $options for the panel: 'keep_review_items' (ids a merge leaves where they are)
     *                       and 'review_item' (the item a merge settles, kept in artist_merges)
     * @return int the operation's id
     */
    public function run($operation, array $args, $userId, $why, $path = 'cli', array $options = array())
    {
        if (!isset(self::OPERATIONS[$operation])) {
            throw new InvalidArgumentException(sprintf('No operation "%s"; there are %s.', $operation, implode(', ', array_keys(self::OPERATIONS))));
        }
        if (count($args) !== count(self::OPERATIONS[$operation])) {
            throw new InvalidArgumentException(sprintf('%s takes %s.', $operation, implode(', ', self::OPERATIONS[$operation])));
        }
        $args = array_combine(self::OPERATIONS[$operation], array_values($args));
        if ('' === trim((string) $why)) {
            throw new InvalidArgumentException('Every edit says why.');
        }
        $this->userId = (int) $userId;
        $this->lines = array();
        $this->_db->query(
            'INSERT INTO edit_operations (operation, args, user_id, why, path) VALUES (?, ?, ?, ?, ?)',
            array($operation, $this->json($args), $this->userId, trim($why), $path)
        );
        $this->operationId = (int) $this->_db->lastInsertId();

        switch ($operation) {
            case 'merge-albums':
                $this->mergeAlbums($this->id($args['from']), $this->id($args['into']));
                break;
            case 'merge-artists':
                $this->mergeArtists($this->id($args['from']), $this->id($args['into']), $options);
                break;
            case 'delete-album':
                $this->deleteAlbum($this->id($args['id']));
                break;
            case 'delete-artist':
                $this->deleteArtist($this->id($args['id']));
                break;
            case 'delete-label':
                $this->deleteLabel($this->id($args['id']));
                break;
            case 'set':
                $this->setField($args['table'], $this->id($args['id']), $args['column'], $args['value']);
                break;
            case 'undo':
                $this->undo($this->id($args['operation']));
                break;
        }
        return $this->operationId;
    }

    /** The album a merged one became, following merges of merges; null for one never merged */
    public function albumMergedInto($albumId)
    {
        $rows = $this->_db->fetchAll('SELECT into_id FROM album_merges WHERE id = ?', array((int) $albumId));
        return empty($rows) ? null : (int) $rows[0]['into_id'];
    }

    /** @return string[] one line per row the last operation changed */
    public function getLines()
    {
        return $this->lines;
    }

    // --- Operations ------------------------------------------------------------------------

    private function mergeAlbums($fromId, $intoId)
    {
        $from = $this->one('albums', $fromId);
        $this->one('albums', $intoId);
        if ($fromId === $intoId) {
            throw new RuntimeException('An album cannot be merged into itself.');
        }
        foreach (self::ALBUM_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->moveAll($table, $column, $fromId, $intoId);
            }
        }
        // An EP or a single whose parent release this was belongs to the album kept (#53).
        $this->moveAll('albums', 'epfor', $fromId, $intoId);
        $this->moveEntities('album', $fromId, $intoId);
        $this->moveAll('album_merges', 'into_id', $fromId, $intoId);
        $this->deleteRow('albums', $from);
        $this->insertRow('album_merges', array('id' => $fromId, 'into_id' => $intoId, 'operation_id' => $this->operationId, 'merged' => $this->now()));
        $this->touch('albums', $intoId);
    }

    private function mergeArtists($fromId, $intoId, array $options)
    {
        $from = $this->one('artists', $fromId);
        $this->one('artists', $intoId);
        if ($fromId === $intoId) {
            throw new RuntimeException('An artist cannot be merged into itself.');
        }
        foreach (self::ARTIST_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->moveAll($table, $column, $fromId, $intoId);
            }
        }
        // A member of the band it is merged into would be its own member.
        foreach ($this->_db->fetchAll('SELECT * FROM band_lookup WHERE artistid = ? AND bandid = ?', array($intoId, $intoId)) as $row) {
            $this->deleteRow('band_lookup', $row);
        }
        $this->moveEntities('artist', $fromId, $intoId, isset($options['keep_review_items']) ? $options['keep_review_items'] : array());
        $this->moveAll('artist_merges', 'into_id', $fromId, $intoId);
        $this->deleteRow('artists', $from);
        $this->insertRow('artist_merges', array(
            'id' => $fromId, 'into_id' => $intoId,
            'review_item_id' => isset($options['review_item']) ? (int) $options['review_item'] : null,
            'merged' => $this->now(),
        ));
        $this->touch('artists', $intoId);
    }

    private function deleteAlbum($id)
    {
        $album = $this->one('albums', $id);
        foreach (self::ALBUM_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->deleteAll($table, "`$column` = ?", array($id));
            }
        }
        // Its EPs and singles stay, without a parent release.
        foreach ($this->_db->fetchAll('SELECT * FROM albums WHERE epfor = ?', array($id)) as $row) {
            $this->changeRow('albums', $row, array('epfor' => null));
        }
        $this->deleteEntities('album', $id);
        $this->deleteAll('album_merges', 'into_id = ?', array($id));
        $this->deleteRow('albums', $album);
    }

    private function deleteArtist($id)
    {
        $artist = $this->one('artists', $id);
        foreach (self::ARTIST_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->deleteAll($table, "`$column` = ?", array($id));
            }
        }
        $this->deleteEntities('artist', $id);
        $this->deleteAll('artist_merges', 'into_id = ?', array($id));
        $this->deleteRow('artists', $artist);
    }

    private function deleteLabel($id)
    {
        $label = $this->one('labels', $id);
        // Its albums stay, with no label (#55).
        foreach ($this->_db->fetchAll('SELECT * FROM albums WHERE labelid = ?', array($id)) as $row) {
            $this->changeRow('albums', $row, array('labelid' => null));
        }
        foreach (self::LABEL_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->deleteAll($table, "`$column` = ?", array($id));
            }
        }
        $this->deleteEntities('label', $id);
        $this->deleteRow('labels', $label);
    }

    /** @param string|null $value null sets the column NULL */
    private function setField($table, $id, $column, $value)
    {
        if (!in_array($table, self::SETTABLE, true)) {
            throw new RuntimeException(sprintf('set changes %s, not %s.', implode(', ', self::SETTABLE), $table));
        }
        $row = $this->one($table, $id);
        if ('id' === $column || !array_key_exists($column, $row)) {
            throw new RuntimeException(sprintf('%s has no column "%s" that set may change.', $table, $column));
        }
        $set = array($column => $value);
        foreach (array('updatedby' => $this->userId, 'updated' => $this->now()) as $audit => $now) {
            if (array_key_exists($audit, $row) && $audit !== $column) {
                $set[$audit] = $now;
            }
        }
        $this->changeRow($table, $row, $set);
    }

    /**
     * Takes an operation back: every row it deleted is inserted again, every row it changed
     * gets its values before, every row it inserted goes, in the reverse of the order it
     * did them. A row that changed again since is refused, and the whole undo with it.
     */
    private function undo($operationId)
    {
        $operation = $this->_db->fetchAll('SELECT * FROM edit_operations WHERE id = ?', array($operationId));
        if (empty($operation)) {
            throw new RuntimeException(sprintf('There is no operation %d.', $operationId));
        }
        if (null !== $operation[0]['undone_by']) {
            throw new RuntimeException(sprintf('Operation %d was undone already, by %d.', $operationId, $operation[0]['undone_by']));
        }
        if ('undo' === $operation[0]['operation']) {
            throw new RuntimeException('An undo is not undone; run the operation again instead.');
        }
        $entries = $this->_db->fetchAll('SELECT * FROM edit_journal WHERE operation_id = ? ORDER BY id DESC', array($operationId));
        foreach ($entries as $entry) {
            $before = null === $entry['row_before'] ? null : json_decode($entry['row_before'], true);
            $after = null === $entry['row_after'] ? null : json_decode($entry['row_after'], true);
            if ('deleted' === $entry['action']) {
                $this->insertRow($entry['table_name'], $before);
                continue;
            }
            $now = $this->find($entry['table_name'], $after);
            if (null === $now || $this->json($now) !== $this->json($after)) {
                throw new RuntimeException(sprintf('A row of %s changed since operation %d, so it cannot be undone: %s', $entry['table_name'], $operationId, $this->json($after)));
            }
            if ('inserted' === $entry['action']) {
                $this->deleteRow($entry['table_name'], $now);
            } else {
                $this->changeRow($entry['table_name'], $now, $before);
            }
        }
        $this->_db->query('UPDATE edit_operations SET undone_by = ? WHERE id = ?', array($this->operationId, $operationId));
    }

    // --- Rows, journalled --------------------------------------------------------------------

    /** Points every row whose $column is $from at $into; one the unique keys refuse is deleted */
    private function moveAll($table, $column, $from, $into)
    {
        foreach ($this->_db->fetchAll("SELECT * FROM `$table` WHERE `$column` = ?", array($from)) as $row) {
            $this->moveRow($table, $row, array($column => $into));
        }
    }

    private function moveEntities($kind, $from, $into, array $keepReviewItems = array())
    {
        foreach (self::ENTITY_TABLES as $table => list($typeColumn, $idColumn)) {
            $rows = $this->_db->fetchAll(
                "SELECT * FROM `$table` WHERE `$typeColumn` = ? AND `$idColumn` = ?",
                array(self::ENTITY_TYPES[$kind][$table], $from)
            );
            foreach ($rows as $row) {
                if ('review_items' === $table && in_array((int) $row['id'], $keepReviewItems, true)) {
                    continue;
                }
                $this->moveRow($table, $row, array($idColumn => $into));
            }
        }
    }

    private function deleteEntities($kind, $id)
    {
        foreach (self::ENTITY_TABLES as $table => list($typeColumn, $idColumn)) {
            $this->deleteAll($table, "`$typeColumn` = ? AND `$idColumn` = ?", array(self::ENTITY_TYPES[$kind][$table], $id));
        }
    }

    private function deleteAll($table, $where, array $values)
    {
        foreach ($this->_db->fetchAll("SELECT * FROM `$table` WHERE $where", $values) as $row) {
            $this->deleteRow($table, $row);
        }
    }

    /** Changes a row, or deletes it when the change would duplicate a row a unique key holds */
    private function moveRow($table, array $row, array $set)
    {
        try {
            $this->changeRow($table, $row, $set);
        } catch (Zend_Db_Statement_Exception $e) {
            if (false === strpos($e->getMessage(), '1062')) {
                throw $e;
            }
            $this->deleteRow($table, $row);
        }
    }

    private function changeRow($table, array $row, array $set)
    {
        list($where, $values) = $this->where($table, $row);
        $assign = array();
        foreach (array_keys($set) as $column) {
            $assign[] = "`$column` = ?";
        }
        $this->_db->query("UPDATE `$table` SET " . implode(', ', $assign) . " WHERE $where LIMIT 1", array_merge(array_values($set), $values));
        $after = $this->find($table, array_merge($row, $set));
        $this->journal($table, 'changed', $row, $after);
    }

    private function deleteRow($table, array $row)
    {
        list($where, $values) = $this->where($table, $row);
        $this->_db->query("DELETE FROM `$table` WHERE $where LIMIT 1", $values);
        $this->journal($table, 'deleted', $row, null);
    }

    private function insertRow($table, array $row)
    {
        $columns = array_keys($row);
        $this->_db->query(
            "INSERT INTO `$table` (`" . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_values($row)
        );
        $this->journal($table, 'inserted', null, $this->find($table, $row));
    }

    private function touch($table, $id)
    {
        $this->changeRow($table, $this->one($table, $id), array('updatedby' => $this->userId, 'updated' => $this->now()));
    }

    private function journal($table, $action, $before, $after)
    {
        $this->_db->query(
            'INSERT INTO edit_journal (operation_id, table_name, action, row_before, row_after) VALUES (?, ?, ?, ?, ?)',
            array($this->operationId, $table, $action, null === $before ? null : $this->json($before), null === $after ? null : $this->json($after))
        );
        $key = $this->keyOf($table, null === $after ? $before : $after);
        $changes = array();
        if ('changed' === $action) {
            foreach ($after as $column => $value) {
                if ((string) $before[$column] !== (string) $value || (null === $value) !== (null === $before[$column])) {
                    $changes[] = sprintf('%s %s → %s', $column, $this->show($before[$column]), $this->show($value));
                }
            }
        }
        $this->lines[] = sprintf(
            '%s %s %s%s',
            array('inserted' => '+', 'changed' => '~', 'deleted' => '-')[$action],
            $table,
            $this->json($key),
            empty($changes) ? '' : ': ' . implode(', ', $changes)
        );
    }

    // --- Finding rows ------------------------------------------------------------------------

    /** The row of $table with that id, or an error that names it */
    private function one($table, $id)
    {
        $rows = $this->_db->fetchAll("SELECT * FROM `$table` WHERE id = ?", array($id));
        if (empty($rows)) {
            throw new RuntimeException(sprintf('There is no %s %d.', rtrim($table, 's'), $id));
        }
        return $rows[0];
    }

    /** The current row with the key of $row, or null */
    private function find($table, array $row)
    {
        list($where, $values) = $this->where($table, $row);
        $rows = $this->_db->fetchAll("SELECT * FROM `$table` WHERE $where LIMIT 1", $values);
        return empty($rows) ? null : $rows[0];
    }

    /** WHERE on the columns that identify one row of $table, null-safe */
    private function where($table, array $row)
    {
        $where = array();
        $values = array();
        foreach ($this->keyOf($table, $row) as $column => $value) {
            $where[] = "`$column` <=> ?";
            $values[] = $value;
        }
        return array(implode(' AND ', $where), $values);
    }

    /** @return array column => value: the primary key, else the first unique key, else every column */
    private function keyOf($table, array $row)
    {
        if (!isset($this->keys[$table])) {
            $indexes = array();
            $rows = $this->_db->fetchAll(
                'SELECT index_name, column_name FROM information_schema.statistics
                  WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 0
                  ORDER BY index_name = "PRIMARY" DESC, index_name, seq_in_index',
                array($table)
            );
            foreach ($rows as $index) {
                $indexes[$index['index_name']][] = $index['column_name'];
            }
            $this->keys[$table] = empty($indexes) ? null : reset($indexes);
        }
        if (null === $this->keys[$table]) {
            return $row;
        }
        return array_intersect_key($row, array_flip($this->keys[$table]));
    }

    private function id($value)
    {
        if (!preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an id.', $value));
        }
        return (int) $value;
    }

    private function now()
    {
        return date('Y-m-d H:i:s');
    }

    private function json($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function show($value)
    {
        if (null === $value) {
            return 'NULL';
        }
        $value = (string) $value;
        return mb_strlen($value, 'UTF-8') > 60 ? '"' . mb_substr($value, 0, 57, 'UTF-8') . '..."' : '"' . $value . '"';
    }
}
