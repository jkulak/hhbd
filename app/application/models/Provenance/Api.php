<?php

/**
 * Where imported data came from: the tables import_runs and import_provenance (#52).
 *
 * The importer opens a run for every batch file it reads, records a row for every field it sets
 * and every source that supplied it, and closes the run with the report's totals. A page asks a
 * row's provenance whether a field came from a given source, to credit it or to hold back what
 * may not be shown.
 */
class Model_Provenance_Api extends Jkl_Model_Api
{
    public const ENTITY_TYPES = array('album', 'artist', 'label', 'song', 'image');

    /**
     * Every source imported data may come from. Those that also give ids have the same name in
     * external_ids (Model_ExternalId_Api::VOCABULARY), so one name means one source everywhere.
     */
    public const SOURCES = array(
        'bandcamp',
        'bn',               // the National Library's catalogue, data.bn.org.pl
        'commons',          // Wikimedia Commons
        'coverartarchive',
        'deezer',
        'discogs',
        'glamrap',
        'itunes',
        'musicbrainz',
        'plwiki',           // the Polish Wikipedia
        'wikidata',
    );

    public const MODES = array('dry-run', 'apply');

    /**
     * The totals of a run, named as the import report names its actions.
     */
    public const COUNTS = array('created', 'updated', 'unchanged', 'skipped', 'refused');

    private static $_instance;

    /**
     * What every run this instance has looked at takes: dry-run, apply or closed, so recording a
     * hundred fields of one run asks the database about the run once.
     */
    private $_runModes = array();

    /**
     * @return Model_Provenance_Api
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @param object|null $db anything with fetchAll(), query() and lastInsertId() taking bound
     *                        values, like Jkl_Db; tests pass a stand-in
     */
    public function __construct($db = null)
    {
        if (null === $db) {
            parent::__construct();
        } else {
            $this->_db = $db;
        }
    }

    /**
     * Opens a run for a batch file.
     *
     * @param string $batch  the file's name, as the report shows it
     * @param string $sha256 the file's SHA-256, so a listing shows when the same file ran twice
     * @param string $mode   dry-run or apply
     * @return int the run's id
     */
    public function startRun($batch, $sha256, $mode)
    {
        $batch = trim((string) $batch);
        if ('' === $batch || strlen($batch) > 190) {
            throw new InvalidArgumentException(sprintf('Not a batch name: "%s"', $batch));
        }
        $sha256 = strtolower(trim((string) $sha256));
        if (!preg_match('/^[0-9a-f]{64}$/', $sha256)) {
            throw new InvalidArgumentException(sprintf('Not a SHA-256: "%s"', $sha256));
        }
        if (!in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown mode "%s"', $mode));
        }

        $this->_db->query(
            'INSERT INTO import_runs (batch, batch_sha256, mode) VALUES (?, ?, ?)',
            array($batch, $sha256, $mode)
        );
        $runId = (int) $this->_db->lastInsertId();
        $this->_runModes[$runId] = $mode;
        return $runId;
    }

    /**
     * Closes a run with its totals and the whole report.
     *
     * @param array      $counts totals by action (created, updated, ...); a missing one is 0
     * @param array|null $report the report as the importer prints it
     * @throws InvalidArgumentException when the run is not open
     */
    public function finishRun($runId, array $counts, $report = null)
    {
        foreach ($counts as $action => $count) {
            if (!in_array($action, self::COUNTS, true)) {
                throw new InvalidArgumentException(sprintf('Unknown count "%s"', $action));
            }
            if (!is_int($count) || $count < 0) {
                throw new InvalidArgumentException(sprintf('Not a count of %s: "%s"', $action, $count));
            }
        }
        if (null !== $report && !is_array($report)) {
            throw new InvalidArgumentException('The report is an array, or null');
        }

        $bind = array();
        foreach (self::COUNTS as $action) {
            $bind[] = isset($counts[$action]) ? $counts[$action] : 0;
        }
        $bind[] = null === $report ? null : json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $bind[] = self::assertId($runId);

        $statement = $this->_db->query(
            'UPDATE import_runs SET finished = current_timestamp(), created_count = ?, updated_count = ?,
                    unchanged_count = ?, skipped_count = ?, refused_count = ?, report = ?
              WHERE id = ? AND finished IS NULL',
            $bind
        );
        if (1 !== $statement->rowCount()) {
            throw new InvalidArgumentException(sprintf('Run %d is not open', $runId));
        }
        $this->_runModes[(int) $runId] = 'closed';
    }

    /**
     * Records that a source supplied a field of a row. Recording the same field from the same
     * source again replaces the reference, licence and time, and points the row at this run.
     *
     * @param string      $field     a column name, or a part with no column: cover, photo, tracklist
     * @param string      $sourceRef the id or URL of what was read, inside the source
     * @param string|null $licence   CC0, CC BY-SA 4.0, ...; null where the field is a bare fact
     * @param string      $fetched   when the source was read: ISO 8601, stored in UTC
     * @throws InvalidArgumentException for an unknown run, a dry run, or a run already closed
     */
    public function record($runId, $entityType, $entityId, $field, $source, $sourceRef, $licence, $fetched)
    {
        $runId = self::assertId($runId);
        self::assertEntity($entityType, $entityId);
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $field)) {
            throw new InvalidArgumentException(sprintf('Not a field name: "%s"', $field));
        }
        if (!in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown source "%s"', $source));
        }
        $sourceRef = trim((string) $sourceRef);
        if ('' === $sourceRef || strlen($sourceRef) > 500) {
            throw new InvalidArgumentException(sprintf('Not a reference: "%s"', $sourceRef));
        }
        if (null !== $licence) {
            $licence = trim((string) $licence);
            if ('' === $licence || strlen($licence) > 64) {
                throw new InvalidArgumentException(sprintf('Not a licence: "%s"', $licence));
            }
        }
        $fetched = self::toUtc($fetched);
        $this->assertRunTakesProvenance($runId);

        $this->_db->query(
            'INSERT INTO import_provenance (entity_type, entity_id, field, source, source_ref, licence, fetched, run_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE source_ref = VALUES(source_ref), licence = VALUES(licence),
                                     fetched = VALUES(fetched), run_id = VALUES(run_id)',
            array($entityType, (int) $entityId, $field, $source, $sourceRef, $licence, $fetched, $runId)
        );
    }

    /**
     * Where every imported field of a row came from, ordered by field and source.
     *
     * @return Model_Provenance_List
     */
    public function getForEntity($entityType, $entityId)
    {
        self::assertEntity($entityType, $entityId);
        $rows = $this->_db->fetchAll(
            'SELECT entity_type, entity_id, field, source, source_ref, licence, fetched, run_id, added
               FROM import_provenance WHERE entity_type = ? AND entity_id = ? ORDER BY field, source',
            array($entityType, (int) $entityId)
        );
        $list = new Model_Provenance_List();
        foreach ($rows as $row) {
            $list->add(new Model_Provenance_Container($row));
        }
        return $list;
    }

    /**
     * A time from a batch as the database keeps it: UTC, to the second. A time without a zone
     * is taken as UTC already.
     *
     * @throws InvalidArgumentException for anything that is not an ISO 8601 date and time
     */
    public static function toUtc($time)
    {
        $time = trim((string) $time);
        $utc = new DateTimeZone('UTC');
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $time)
            ? date_create($time, $utc)
            : false;
        $errors = date_get_last_errors();
        if (false === $parsed || ($errors && $errors['warning_count'] > 0)) {
            throw new InvalidArgumentException(sprintf('Not a date and time: "%s"', $time));
        }
        return $parsed->setTimezone($utc)->format('Y-m-d H:i:s');
    }

    /**
     * A dry run writes nothing but its own row, and a closed run nothing at all; provenance from
     * either would describe data that was never written, or written by another run.
     */
    private function assertRunTakesProvenance($runId)
    {
        if (!isset($this->_runModes[$runId])) {
            $rows = $this->_db->fetchAll('SELECT mode, finished FROM import_runs WHERE id = ?', array($runId));
            if (empty($rows)) {
                throw new InvalidArgumentException(sprintf('No run %d', $runId));
            }
            $this->_runModes[$runId] = null === $rows[0]['finished'] ? $rows[0]['mode'] : 'closed';
        }
        if ('closed' === $this->_runModes[$runId]) {
            throw new InvalidArgumentException(sprintf('Run %d is closed', $runId));
        }
        if ('apply' !== $this->_runModes[$runId]) {
            throw new InvalidArgumentException(sprintf('Run %d is a dry run, which records no provenance', $runId));
        }
    }

    private static function assertId($id)
    {
        if ((int) $id < 1 || (string) (int) $id !== (string) $id) {
            throw new InvalidArgumentException(sprintf('Not an id: "%s"', $id));
        }
        return (int) $id;
    }

    private static function assertEntity($entityType, $entityId)
    {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown entity type "%s"', $entityType));
        }
        self::assertId($entityId);
    }
}
