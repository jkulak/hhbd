<?php

/**
 * An admin's edits to the catalogue, each journalled and each undoable (#115): merging two
 * albums or two artists, deleting an album, an artist or a label, setting a row's fields,
 * releasing a queued album. The command line (app/tools/edit.php), the review panel's merge
 * (#103) and the daily release (#258) all come here, so a change leaves the same record
 * whichever way it came.
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
        'release'       => array('id'),
        'undo'          => array('operation'),
    );

    /** The ways an operation arrives: an admin's command line or panel, or a scheduled job */
    public const PATHS = array('cli', 'panel', 'job');

    /**
     * The columns a visit changes, the page views (#235), which undo neither compares nor puts
     * back: a page seen since the operation is no change to refuse it for.
     */
    public const COUNTERS = array('viewed');

    /** The song links whose artists a song's page names, as table => artist column */
    public const SONG_CREDITS = array(
        'artist_lookup'  => 'artistid',
        'feature_lookup' => 'artistid',
        'music_lookup'   => 'artistid',
        'remix_lookup'   => 'artistid',
        'scratch_lookup' => 'artistid',
    );

    /**
     * set's other form: a row's columns and their values as one JSON object, so ten columns are
     * one operation and one undo rather than ten (#145)
     */
    public const SET_COLUMNS = array('table', 'id', 'values');

    /** The tables whose fields `set` changes */
    public const SETTABLE = array('albums', 'artists', 'labels', 'songs');

    /**
     * The columns the JSON form may not name: the id is the row, and set writes the other two
     * itself. One column at a time may still correct them.
     */
    public const SET_OWN = array('id', 'updatedby', 'updated');

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

    /** The users row the edits are written as, in updatedby (#132) */
    private $auditUserId;
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
        $rows = $this->_db->fetchAll("SELECT usr_id FROM hhb_users WHERE lower(usr_display_name) = lower(?) AND usr_is_admin = 'yes'", array((string) $name));
        return 1 === count($rows) ? (int) $rows[0]['usr_id'] : null;
    }

    /**
     * Runs one operation and journals it, inside the caller's transaction.
     *
     * @param int    $userId  the admin's hhb_users account; for a job, the users row it writes as
     * @param array  $options for the panel: 'keep_review_items' (ids a merge leaves where they are)
     *                       and 'review_item' (the item a merge settles, kept in artist_merges)
     * @return int the operation's id
     */
    public function run($operation, array $args, $userId, $why, $path = 'cli', array $options = array())
    {
        if (!in_array($path, self::PATHS, true)) {
            throw new InvalidArgumentException(sprintf('No path "%s"; there are %s.', $path, implode(', ', self::PATHS)));
        }
        if (!isset(self::OPERATIONS[$operation])) {
            throw new InvalidArgumentException(sprintf('No operation "%s"; there are %s.', $operation, implode(', ', array_keys(self::OPERATIONS))));
        }
        $names = self::OPERATIONS[$operation];
        if ('set' === $operation && count($args) === count(self::SET_COLUMNS)) {
            $names = self::SET_COLUMNS;
        }
        if (count($args) !== count($names)) {
            throw new InvalidArgumentException(sprintf(
                '%s takes %s%s.',
                $operation,
                implode(', ', $names),
                'set' === $operation ? ', or ' . implode(', ', self::SET_COLUMNS) . ' as a JSON object' : ''
            ));
        }
        $args = array_combine($names, array_values($args));
        if (array_key_exists('values', $args)) {
            // Decoded before the journal has it, so edit_operations keeps the object, not a string.
            $args['values'] = self::columnsOf($args['values']);
        }
        if ('' === trim((string) $why)) {
            throw new InvalidArgumentException('Every edit says why.');
        }
        $this->userId = (int) $userId;
        // A job has no account; it writes as its users row, the import's for the release.
        $this->auditUserId = 'job' === $path ? $this->userId : Model_Audit_Api::getInstance()->userIdFor($this->userId);
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
                if (array_key_exists('values', $args)) {
                    $this->setColumns($args['table'], $this->id($args['id']), $args['values']);
                } else {
                    $this->setColumns($args['table'], $this->id($args['id']), array($args['column'] => $args['value']), false);
                }
                break;
            case 'release':
                $this->release($this->id($args['id']));
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

    /**
     * The columns of set's JSON form, from VALUE: an object of at least one column, each value a
     * string, a number or null. Anything else is a wrong call, refused before a row is read.
     *
     * @return array column => value
     */
    public static function columnsOf($json)
    {
        $decoded = null === $json ? null : json_decode((string) $json, false, 512, JSON_BIGINT_AS_STRING);
        if (!$decoded instanceof stdClass) {
            throw new InvalidArgumentException('set <table> <id> takes its columns as a JSON object in VALUE: {"column": "value", ...}.');
        }
        $values = get_object_vars($decoded);
        if (empty($values)) {
            throw new InvalidArgumentException('The JSON object in VALUE names no column.');
        }
        foreach ($values as $column => $value) {
            if (null !== $value && !is_string($value) && !is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException(sprintf('"%s" in VALUE is %s; a column takes a string, a number or null.', $column, json_encode($value)));
            }
        }
        return $values;
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
        // The kept album keeps the cover it shows: the merged one's files come along, but not as
        // its main cover, which the page would otherwise take as the newer one.
        $keepsCover = !empty($this->_db->fetchAll("SELECT id FROM album_covers WHERE albumid = ? AND main = 'y' LIMIT 1", array($intoId)));
        foreach (self::ALBUM_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if ('album_covers' === $table && $keepsCover) {
                    foreach ($this->_db->fetchAll('SELECT * FROM album_covers WHERE albumid = ?', array($fromId)) as $row) {
                        $this->moveRow('album_covers', $row, array('albumid' => $intoId, 'main' => 'n'));
                    }
                    continue;
                }
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
        $into = $this->one('artists', $intoId);
        if ($fromId === $intoId) {
            throw new RuntimeException('An artist cannot be merged into itself.');
        }
        foreach (self::ARTIST_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if ('altnames_lookup' === $table) {
                    $this->moveNames($from, $into);
                    continue;
                }
                $this->moveAll($table, $column, $fromId, $intoId);
            }
        }
        // A member of the band it is merged into would be its own member.
        foreach ($this->_db->fetchAll('SELECT * FROM band_lookup WHERE artistid = ? AND bandid = ?', array($intoId, $intoId)) as $row) {
            $this->deleteRow('band_lookup', $row);
        }
        $keepReviewItems = isset($options['keep_review_items']) ? $options['keep_review_items'] : array();
        $this->closeDuplicates($fromId, $intoId, $keepReviewItems);
        $this->moveEntities('artist', $fromId, $intoId, $keepReviewItems);
        $this->moveAll('artist_merges', 'into_id', $fromId, $intoId);
        $this->deleteRow('artists', $from);
        $this->insertRow('artist_merges', array(
            'id' => $fromId, 'into_id' => $intoId,
            'review_item_id' => isset($options['review_item']) ? (int) $options['review_item'] : null,
            'merged' => $this->now(),
        ));
        $this->touch('artists', $intoId);
    }

    /**
     * The merged artist's alternative names go to the kept one, and so does its name (#260):
     * whoever searches for it still finds the artist. A name the kept artist has already, as
     * its name or as an alternative one, is not added twice, and an alternative name that is
     * the kept artist's own name is none.
     */
    private function moveNames(array $from, array $into)
    {
        $rows = $this->_db->fetchAll(
            'SELECT *, CASE WHEN lower(altname) = lower(?) THEN 1 ELSE 0 END AS is_name FROM altnames_lookup WHERE artistid = ?',
            array($into['name'], $from['id'])
        );
        foreach ($rows as $row) {
            $isName = 1 === (int) $row['is_name'];
            unset($row['is_name']);
            if ($isName) {
                $this->deleteRow('altnames_lookup', $row);
            } else {
                $this->moveRow('altnames_lookup', $row, array('artistid' => $into['id']));
            }
        }
        $known = $this->_db->fetchAll(
            'SELECT 1 FROM artists WHERE id = ? AND lower(name) = lower(?)
             UNION ALL SELECT 1 FROM altnames_lookup WHERE artistid = ? AND lower(altname) = lower(?)',
            array($into['id'], $from['name'], $into['id'], $from['name'])
        );
        if (empty($known)) {
            $this->insertRow('altnames_lookup', array('artistid' => (int) $into['id'], 'altname' => $from['name'], 'status' => 999));
        }
    }

    /**
     * The open items asking whether the two are one artist (#192), on either row, are answered
     * by the merge: closed as merged, by whoever merges, journalled with it. The one the panel's
     * merge settles itself is left to it.
     */
    private function closeDuplicates($fromId, $intoId, array $keepReviewItems)
    {
        $rows = $this->_db->fetchAll(
            "SELECT * FROM review_items WHERE entity_type = 'artist' AND entity_id IN (?, ?) AND reason = 'duplicate' AND resolved IS NULL",
            array($fromId, $intoId)
        );
        foreach ($rows as $row) {
            $item = new Model_Review_Container($row);
            $other = $item->entityId === $fromId ? $intoId : $fromId;
            if (in_array($item->id, $keepReviewItems, true) || !in_array($other, $item->getSuggestions(), true)) {
                continue;
            }
            $this->changeRow('review_items', $row, array(
                'resolved'    => $this->now(),
                'resolved_by' => $this->userId,
                'resolution'  => Model_Review_Api::RESOLUTIONS['merge'],
                'note'        => sprintf('edit operation %d merged artist %d into %d', $this->operationId, $fromId, $intoId),
            ));
        }
    }

    private function deleteAlbum($id)
    {
        $album = $this->one('albums', $id);
        foreach (self::ALBUM_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->deleteAll($table, $this->q($column) . ' = ?', array($id));
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
                $this->deleteAll($table, $this->q($column) . ' = ?', array($id));
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
                $this->deleteAll($table, $this->q($column) . ' = ?', array($id));
            }
        }
        $this->deleteEntities('label', $id);
        $this->deleteRow('labels', $label);
    }

    /**
     * Publishes an album from the release queue (#258), as the daily job does with the next few:
     * shown from now and added now, so it leads the lists of what the catalogue gained like an
     * album an admin added today. So do the songs nothing published showed before, and the
     * artists that waited for it: those it credits, those its songs credit, and the members of
     * the bands it credits. Undone, all of it waits again, the album in its old place.
     */
    private function release($id)
    {
        $album = $this->one('albums', $id);
        if (Model_Album_Api::PUBLISHED === (int) $album['status']) {
            throw new RuntimeException(sprintf('Album %d is published already.', $id));
        }
        if (null === $album['queued']) {
            throw new RuntimeException(sprintf('Album %d is not in the release queue.', $id));
        }
        $now = $this->now();
        $songs = $this->_db->fetchAll(
            'SELECT s.* FROM songs s WHERE s.id IN (SELECT songid FROM album_lookup WHERE albumid = ?)
               AND NOT EXISTS (SELECT 1 FROM album_lookup l JOIN albums a ON a.id = l.albumid
                               WHERE l.songid = s.id AND a.id <> ? AND a.status = ?)
             ORDER BY s.id',
            array($id, $id, Model_Album_Api::PUBLISHED)
        );
        $named = array('SELECT artistid FROM album_artist_lookup WHERE albumid = ?');
        foreach (self::SONG_CREDITS as $table => $column) {
            $named[] = 'SELECT ' . $this->q($column) . ' FROM ' . $this->q($table) . ' WHERE songid IN (SELECT songid FROM album_lookup WHERE albumid = ?)';
        }
        $named[] = 'SELECT artistid FROM band_lookup WHERE bandid IN (SELECT artistid FROM album_artist_lookup WHERE albumid = ?)';
        $artists = $this->_db->fetchAll(
            'SELECT * FROM artists WHERE queued IS NOT NULL AND id IN (' . implode(' UNION ', $named) . ') ORDER BY id',
            array_fill(0, count($named), $id)
        );

        $this->changeRow('albums', $album, array('status' => Model_Album_Api::PUBLISHED, 'queued' => null, 'added' => $now));
        foreach ($songs as $song) {
            $this->changeRow('songs', $song, array('added' => $now));
        }
        foreach ($artists as $artist) {
            $this->changeRow('artists', $artist, array('queued' => null, 'added' => $now));
        }
    }

    /**
     * Sets a row's columns in one change, a null value to NULL, with updatedby and updated as
     * the admin and now. A column the row lacks refuses them all.
     *
     * @param bool $json the JSON form, which may not name SET_OWN; one column may name all but id
     */
    private function setColumns($table, $id, array $values, $json = true)
    {
        if (!in_array($table, self::SETTABLE, true)) {
            throw new RuntimeException(sprintf('set changes %s, not %s.', implode(', ', self::SETTABLE), $table));
        }
        $row = $this->one($table, $id);
        foreach (array_keys($values) as $column) {
            $column = (string) $column;
            if (!array_key_exists($column, $row) || 'id' === $column) {
                throw new RuntimeException(sprintf('%s has no column "%s" that set may change.', $table, $column));
            }
            if ($json && in_array($column, self::SET_OWN, true)) {
                throw new RuntimeException(sprintf('set writes %s itself; leave it out of VALUE.', $column));
            }
        }
        $set = $values;
        foreach (array('updatedby' => $this->auditUserId, 'updated' => $this->now()) as $audit => $now) {
            if (array_key_exists($audit, $row) && !array_key_exists($audit, $set)) {
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
            if (null === $now || $this->json($this->uncounted($now)) !== $this->json($this->uncounted($after))) {
                throw new RuntimeException(sprintf('A row of %s changed since operation %d, so it cannot be undone: %s', $entry['table_name'], $operationId, $this->json($after)));
            }
            if ('inserted' === $entry['action']) {
                $this->deleteRow($entry['table_name'], $now);
            } else {
                $this->changeRow($entry['table_name'], $now, $this->uncounted($before));
            }
        }
        $this->_db->query('UPDATE edit_operations SET undone_by = ? WHERE id = ?', array($this->operationId, $operationId));
    }

    /** A row without its COUNTERS */
    private function uncounted(array $row)
    {
        return array_diff_key($row, array_flip(self::COUNTERS));
    }

    // --- Rows, journalled --------------------------------------------------------------------

    /** Points every row whose $column is $from at $into; one the unique keys refuse is deleted */
    private function moveAll($table, $column, $from, $into)
    {
        foreach ($this->_db->fetchAll('SELECT * FROM ' . $this->q($table) . ' WHERE ' . $this->q($column) . ' = ?', array($from)) as $row) {
            $this->moveRow($table, $row, array($column => $into));
        }
    }

    private function moveEntities($kind, $from, $into, array $keepReviewItems = array())
    {
        foreach (self::ENTITY_TABLES as $table => list($typeColumn, $idColumn)) {
            $rows = $this->_db->fetchAll(
                'SELECT * FROM ' . $this->q($table) . ' WHERE ' . $this->q($typeColumn) . ' = ? AND ' . $this->q($idColumn) . ' = ?',
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
            $this->deleteAll($table, $this->q($typeColumn) . ' = ? AND ' . $this->q($idColumn) . ' = ?', array(self::ENTITY_TYPES[$kind][$table], $id));
        }
    }

    private function deleteAll($table, $where, array $values)
    {
        foreach ($this->_db->fetchAll('SELECT * FROM ' . $this->q($table) . ' WHERE ' . $where, $values) as $row) {
            $this->deleteRow($table, $row);
        }
    }

    /**
     * Changes a row, or deletes it when the change would duplicate a row a unique key holds.
     * The change runs under a savepoint, as an operation runs in a transaction: PostgreSQL
     * refuses every statement after a failed one until the transaction goes back past it.
     */
    private function moveRow($table, array $row, array $set)
    {
        $this->_db->query('SAVEPOINT edit_move_row');
        try {
            $this->changeRow($table, $row, $set);
            $this->_db->query('RELEASE SAVEPOINT edit_move_row');
        } catch (Zend_Db_Statement_Exception $e) {
            if (!Jkl_Db::isDuplicateKey($e)) {
                throw $e;
            }
            $this->_db->query('ROLLBACK TO SAVEPOINT edit_move_row');
            $this->deleteRow($table, $row);
        }
    }

    private function changeRow($table, array $row, array $set)
    {
        list($where, $values) = $this->where($table, $row);
        $assign = array();
        foreach (array_keys($set) as $column) {
            $assign[] = $this->q($column) . ' = ?';
        }
        $this->_db->updateOne($this->q($table), implode(', ', $assign), $where, array_merge(array_values($set), $values));
        $after = $this->find($table, array_merge($row, $set));
        $this->journal($table, 'changed', $row, $after);
    }

    private function deleteRow($table, array $row)
    {
        list($where, $values) = $this->where($table, $row);
        $this->_db->deleteOne($this->q($table), $where, $values);
        $this->journal($table, 'deleted', $row, null);
    }

    private function insertRow($table, array $row)
    {
        $columns = array_keys($row);
        $this->_db->query(
            'INSERT INTO ' . $this->q($table) . ' (' . implode(', ', array_map(array($this, 'q'), $columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_values($row)
        );
        $this->journal($table, 'inserted', null, $this->find($table, $row));
    }

    private function touch($table, $id)
    {
        $this->changeRow($table, $this->one($table, $id), array('updatedby' => $this->auditUserId, 'updated' => $this->now()));
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
        $rows = $this->_db->fetchAll('SELECT * FROM ' . $this->q($table) . ' WHERE id = ?', array($id));
        if (empty($rows)) {
            throw new RuntimeException(sprintf('There is no %s %d.', rtrim($table, 's'), $id));
        }
        return $rows[0];
    }

    /** The current row with the key of $row, or null */
    private function find($table, array $row)
    {
        list($where, $values) = $this->where($table, $row);
        $rows = $this->_db->fetchAll('SELECT * FROM ' . $this->q($table) . ' WHERE ' . $where . ' LIMIT 1', $values);
        return empty($rows) ? null : $rows[0];
    }

    /** WHERE on the columns that identify one row of $table, a NULL matched by IS NULL */
    private function where($table, array $row)
    {
        $where = array();
        $values = array();
        foreach ($this->keyOf($table, $row) as $column => $value) {
            if (null === $value) {
                $where[] = $this->q($column) . ' IS NULL';
            } else {
                $where[] = $this->q($column) . ' = ?';
                $values[] = $value;
            }
        }
        return array(implode(' AND ', $where), $values);
    }

    /** @return array column => value: the primary key, else the first unique key, else every column */
    private function keyOf($table, array $row)
    {
        if (!isset($this->keys[$table])) {
            $indexes = $this->_db->uniqueKeys($table);
            $this->keys[$table] = empty($indexes) ? null : reset($indexes);
        }
        if (null === $this->keys[$table]) {
            return $row;
        }
        return array_intersect_key($row, array_flip($this->keys[$table]));
    }

    /** A table's or a column's name, quoted as the engine quotes them */
    private function q($name)
    {
        return $this->_db->quoteIdentifier($name);
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
