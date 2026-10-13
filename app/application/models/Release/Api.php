<?php

/**
 * The release queue (#258). An album an import would publish waits in it, unpublished, with
 * the artists the import made for it; the daily job (app/tools/release.php, the host's
 * hhbd-release timer) publishes the next few, between MIN and MAX of them, so the catalogue
 * grows by a handful of albums every day rather than by a year at once.
 *
 * The queue is albums.queued: an album waits while it has one and is not published, so an
 * admin who publishes it takes it out. The longest-waiting come out first, and among those
 * queued together, the oldest release first. Each release is an operation of the edit journal
 * (Model_Edit_Api's release), which make ovh-edit undoes like any other.
 */
class Model_Release_Api extends Jkl_Model_Api
{
    /** How many albums a day's release publishes at least, and at most, without RELEASE_MIN and RELEASE_MAX */
    public const MIN = 5;
    public const MAX = 10;

    /** An album in the queue */
    private const WAITING = 'a.queued IS NOT NULL AND a.status <> ' . Model_Album_Api::PUBLISHED;

    /** The order they come out in */
    private const ORDER = 'a.queued, a.year IS NULL, a.year, a.id';

    private static $_instance;

    /** @return Model_Release_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * The albums waiting, in the order they come out, each with its main artists' names
     *
     * @param int|null $limit at most this many, the first ones; null for all
     * @return array rows: id, title, year, queued, artists
     */
    public function waiting($limit = null)
    {
        return $this->_db->fetchAll(
            "SELECT a.id, a.title, a.year, a.queued,
                    (SELECT string_agg(r.name, ', ' ORDER BY c.position, c.artistid) FROM album_artist_lookup c
                     JOIN artists r ON r.id = c.artistid WHERE c.albumid = a.id AND c.role = 'main') AS artists
             FROM albums a WHERE " . self::WAITING . ' ORDER BY ' . self::ORDER .
            (null === $limit ? '' : ' LIMIT ' . (int) $limit)
        );
    }

    /** @return int how many albums wait */
    public function count()
    {
        $rows = $this->_db->fetchAll('SELECT COUNT(*) AS n FROM albums a WHERE ' . self::WAITING);
        return (int) $rows[0]['n'];
    }

    /** @return array release year => how many albums of it wait, a year unknown as '' */
    public function countByYear()
    {
        $counts = array();
        $rows = $this->_db->fetchAll(
            'SELECT CAST(EXTRACT(YEAR FROM a.year) AS integer) AS y, COUNT(*) AS n FROM albums a
             WHERE ' . self::WAITING . ' GROUP BY 1 ORDER BY 1 IS NULL, 1'
        );
        foreach ($rows as $row) {
            $counts[null === $row['y'] ? '' : (string) $row['y']] = (int) $row['n'];
        }
        return $counts;
    }

    /**
     * Where an album stands in the queue, for its page (#258)
     *
     * @return array|null place (1 is next), of (how many wait) and queued; null when it does not wait
     */
    public function place($albumId)
    {
        $rows = $this->_db->fetchAll(
            'SELECT place, n, queued FROM (SELECT a.id, a.queued, ROW_NUMBER() OVER (ORDER BY ' . self::ORDER . ') AS place, COUNT(*) OVER () AS n
             FROM albums a WHERE ' . self::WAITING . ') q WHERE id = ?',
            array((int) $albumId)
        );
        return empty($rows) ? null : array('place' => (int) $rows[0]['place'], 'of' => (int) $rows[0]['n'], 'queued' => $rows[0]['queued']);
    }

    /**
     * The artists that wait with no unpublished album to bring them out, queued or lacking a
     * part: made by an import for no album of theirs, or whose albums an admin published or
     * deleted since. An admin shows one with `set artists <id> queued --null`.
     *
     * @return array rows: id, name
     */
    public function stranded()
    {
        $unpublished = 'a.status <> ' . Model_Album_Api::PUBLISHED;
        $named = array('SELECT c.artistid FROM album_artist_lookup c JOIN albums a ON a.id = c.albumid WHERE ' . $unpublished);
        foreach (Model_Edit_Api::SONG_CREDITS as $table => $column) {
            $named[] = 'SELECT k.' . $column . ' FROM ' . $table . ' k JOIN album_lookup l ON l.songid = k.songid JOIN albums a ON a.id = l.albumid WHERE ' . $unpublished;
        }
        $named[] = 'SELECT b.artistid FROM band_lookup b JOIN album_artist_lookup c ON c.artistid = b.bandid JOIN albums a ON a.id = c.albumid WHERE ' . $unpublished;
        return $this->_db->fetchAll(
            'SELECT id, name FROM artists WHERE queued IS NOT NULL AND id NOT IN (' . implode(' UNION ', $named) . ') ORDER BY id'
        );
    }

    /**
     * How many albums a run releases: a number from MIN to MAX, a different one each day, so
     * the catalogue does not grow by the same count like clockwork
     *
     * @param callable $random random_int's signature
     */
    public static function howMany($min, $max, $random = 'random_int')
    {
        self::assertRange($min, $max);
        return (int) call_user_func($random, (int) $min, (int) $max);
    }

    /**
     * The day the last album waiting comes out at the average pace, one run a day from today
     *
     * @param string $today Y-m-d
     * @return string|null Y-m-d, null when nothing waits or nothing is released
     */
    public static function emptiesOn($waiting, $min, $max, $today)
    {
        self::assertRange($min, $max);
        $pace = ($min + $max) / 2;
        if ($waiting <= 0 || $pace <= 0) {
            return null;
        }
        $days = (int) ceil($waiting / $pace) - 1;
        return date('Y-m-d', strtotime($today . ' +' . $days . ' days'));
    }

    /**
     * Releases the next albums, each in its own transaction, so one refused leaves the rest out
     *
     * @param int    $count  how many
     * @param int    $userId the users row the release writes as: the import's (#63)
     * @param string $why    for the journal
     * @return array one entry per album: id, title, and operation and lines, or error
     */
    public function release($count, $userId, $why)
    {
        $edits = Model_Edit_Api::getInstance();
        $done = array();
        foreach ($this->waiting($count) as $album) {
            $entry = array('id' => (int) $album['id'], 'title' => $album['title']);
            $this->_db->beginTransaction();
            try {
                $entry['operation'] = $edits->run('release', array($album['id']), $userId, $why, 'job');
                $entry['lines'] = $edits->getLines();
                $this->_db->commit();
            } catch (Exception $e) {
                $this->_db->rollBack();
                $entry['error'] = $e->getMessage();
            }
            $done[] = $entry;
        }
        return $done;
    }

    private static function assertRange($min, $max)
    {
        if ((int) $min < 0 || (int) $max < (int) $min) {
            throw new InvalidArgumentException(sprintf('A release publishes from MIN to MAX albums, 0 <= MIN <= MAX; not %s to %s.', $min, $max));
        }
    }
}
