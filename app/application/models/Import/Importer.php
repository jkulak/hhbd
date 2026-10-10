<?php

/**
 * Reads an import batch into the catalogue (#56): labels, artists, releases with their
 * tracklists and covers, and images for rows hhbd already has, as app/docs/import.schema.json
 * describes them and docs/import.md explains.
 *
 * Every document is validated against the schema, then matched to a row by its external ids,
 * its hhbd id and its natural key, in that order; only a document that matches nothing makes a
 * row. A row that exists is filled where it is empty and never overwritten: a value that
 * differs from the catalogue's is a warning in the report, for a person to decide. So the same
 * batch read twice reports every document unchanged the second time.
 *
 * A dry run reads the whole batch in one transaction, each document behind a savepoint, and
 * rolls it all back, so documents can refer to rows earlier ones would make and nothing stays
 * but the run's own record; its images are written to a temporary directory and deleted, so a
 * file that cannot be decoded shows up there too. An apply commits each document on its own,
 * and moves its images into content/ only once its rows are in.
 */
class Model_Import_Importer extends Jkl_Model_Api
{
    public const ACTIONS = array('created', 'updated', 'unchanged', 'skipped', 'refused');

    /** At most this many photos per artist (#96). */
    public const MAX_PHOTOS = 5;

    /**
     * The kinds of external id a row has at most one of. The others may repeat: an album has
     * a barcode and a Discogs or MusicBrainz release for every pressing, a song an ISRC for
     * every master.
     */
    private const ONE_PER_ROW = array(
        'discogs:master', 'discogs:artist', 'discogs:label', 'musicbrainz:release_group', 'musicbrainz:artist',
        'musicbrainz:label', 'musicbrainz:recording', 'wikidata:item', 'plwiki:pageid', 'deezer:artist', 'itunes:artist',
    );

    /** The tables behind the entities a reference may name */
    private const TABLES = array('album' => 'albums', 'artist' => 'artists', 'label' => 'labels', 'song' => 'songs');

    private $schema;
    private $contentDir;
    private $batchDir;
    private $mode;
    private $userId;
    private $runId;
    private $scratchDir;

    /** The rows documents of this batch were read into: ref => array(entity, id) */
    private $refs = array();

    /** Images written for the document being read: staging path => final path */
    private $staged = array();

    /** Warnings for the document being read */
    private $warnings = array();

    /**
     * What the document being read changed: provenance groups (core, facts, tracklist, cover,
     * photos, logo) as keys, and ids for its external ids, which have no group.
     */
    private $touched = array();

    private $externalIds;
    private $provenance;
    private $featTypes;
    private $images;
    private $reviews;

    /**
     * @param string $contentDir where content/ is: covers under a/, photos under p/, logos under l/
     */
    public function __construct($contentDir)
    {
        parent::__construct();
        $this->contentDir = rtrim($contentDir, '/');
        $this->schema = Jkl_JsonSchema::fromFile(APPLICATION_PATH . '/../docs/import.schema.json');
        $this->externalIds = Model_ExternalId_Api::getInstance();
        $this->provenance = Model_Provenance_Api::getInstance();
        $this->featTypes = Model_FeatType_Api::getInstance();
        $this->images = Model_Image_Api::getInstance();
        $this->reviews = Model_Review_Api::getInstance();
    }

    /**
     * Reads a batch: a directory holding one .ndjson file and the images it names under files/.
     *
     * @param string        $batchDir
     * @param string        $mode     dry-run or apply
     * @param callable|null $progress called with a line of text after each document
     * @return array the report: run, mode, batch, totals, documents
     */
    public function import($batchDir, $mode, $progress = null)
    {
        if (!in_array($mode, Model_Provenance_Api::MODES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown mode "%s"', $mode));
        }
        $this->batchDir = rtrim($batchDir, '/');
        $this->mode = $mode;
        $files = glob($this->batchDir . '/*.ndjson');
        if (1 !== count($files)) {
            throw new InvalidArgumentException(sprintf('%s holds %d .ndjson files; a batch is one', $batchDir, count($files)));
        }
        $file = $files[0];
        if ('apply' === $mode && !is_writable($this->contentDir)) {
            throw new RuntimeException(sprintf('%s is not writable; is the content volume mounted read-write?', $this->contentDir));
        }
        // Before any write: a database without the import's own user is not ready for it (#63).
        $this->userId = $this->provenance->getImportUserId();
        $this->runId = $this->provenance->startRun(basename($file), hash_file('sha256', $file), $mode);
        $this->refs = array();

        $lines = array_values(array_filter(file($file, FILE_IGNORE_NEW_LINES), function ($line) {
            return '' !== trim($line);
        }));
        $documents = array();
        if ('dry-run' === $mode) {
            $this->scratchDir = sys_get_temp_dir() . '/hhbd-import-' . $this->runId;
            $this->_db->beginTransaction();
        }
        try {
            foreach ($lines as $i => $line) {
                $documents[] = $result = $this->readLine($line, $i + 1);
                if (null !== $progress) {
                    call_user_func($progress, sprintf('%d/%d %s %s', $i + 1, count($lines), $result['action'], $result['ref'] ?: $result['kind']));
                }
            }
        } finally {
            if ('dry-run' === $mode) {
                $this->_db->rollBack();
                $this->removeScratch();
            }
        }

        $totals = array_fill_keys(self::ACTIONS, 0);
        foreach ($documents as $document) {
            $totals[$document['action']]++;
        }
        $report = array(
            'run'       => $this->runId,
            'mode'      => $mode,
            'batch'     => basename($file),
            'totals'    => $totals,
            'documents' => $documents,
        );
        $this->provenance->finishRun($this->runId, $totals, $report);
        return $report;
    }

    private function readLine($line, $number)
    {
        $result = array(
            'line' => $number, 'kind' => null, 'ref' => null, 'action' => 'refused',
            'entity' => null, 'hhbd_id' => null, 'url' => null, 'warnings' => array(), 'errors' => array(),
        );
        $document = json_decode($line, true);
        if (!is_array($document)) {
            $result['errors'][] = 'not a JSON object: ' . json_last_error_msg();
            return $result;
        }
        $result['kind'] = isset($document['kind']) ? $document['kind'] : null;
        $result['ref'] = isset($document['ref']) ? $document['ref'] : (isset($document['target']['ref']) ? $document['target']['ref'] : null);
        $errors = $this->schema->validate($document);
        if (!empty($errors)) {
            $result['errors'] = $errors;
            return $result;
        }

        $this->warnings = array();
        $this->staged = array();
        $this->touched = array();
        $apply = 'apply' === $this->mode;
        $apply ? $this->_db->beginTransaction() : $this->_db->query('SAVEPOINT import_document');
        try {
            list($entity, $id, $created) = $this->readDocument($document);
            if ($apply) {
                $this->recordProvenance($entity, $id, $document);
            }
            $apply ? $this->_db->commit() : $this->_db->query('RELEASE SAVEPOINT import_document');
            if ($apply) {
                $this->moveStaged();
            }
            $result['action'] = $created ? 'created' : (empty($this->touched) ? 'unchanged' : 'updated');
            $result['entity'] = $entity;
            $result['hhbd_id'] = $id;
            $result['url'] = $apply ? $this->urlOf($entity, $id) : null;
            if (isset($document['ref'])) {
                $this->refs[$document['ref']] = array($entity, $id);
            }
        } catch (Exception $e) {
            $apply ? $this->_db->rollBack() : $this->_db->query('ROLLBACK TO SAVEPOINT import_document');
            $this->dropStaged();
            $result['errors'][] = $e->getMessage();
        }
        $result['warnings'] = $this->warnings;
        return $result;
    }

    /**
     * @return array array(entity, id, created)
     */
    private function readDocument(array $document)
    {
        switch ($document['kind']) {
            case 'label':
                return $this->readLabel($document);
            case 'artist':
                return $this->readArtist($document);
            case 'release':
                return $this->readRelease($document);
            case 'image':
                return $this->readImage($document);
        }
        throw new InvalidArgumentException(sprintf('Unknown kind "%s"', $document['kind']));
    }

    // --- Finding rows ----------------------------------------------------------------------

    /**
     * The row a document is about, or null: by its external ids, then its hhbd id, then its
     * natural key. Two ids naming two rows are a conflict, not a choice (#51).
     */
    private function match($entity, array $document, callable $naturalKey)
    {
        $found = null;
        foreach ($this->idsOf($document) as list($source, $kind, $value)) {
            $owner = $this->externalIds->findEntity($source, $kind, $value);
            if (null === $owner) {
                continue;
            }
            if ($owner['entity_type'] !== $entity) {
                throw new RuntimeException(sprintf('%s:%s:%s belongs to %s %d, not to an %s', $source, $kind, $value, $owner['entity_type'], $owner['entity_id'], $entity));
            }
            if (null !== $found && $found !== $owner['entity_id']) {
                throw new RuntimeException(sprintf('Its external ids name two %ss, %d and %d', $entity, $found, $owner['entity_id']));
            }
            $found = $owner['entity_id'];
        }
        if (null !== $found) {
            return $found;
        }
        if (!empty($document['hhbd_id'])) {
            if (!$this->exists($entity, $document['hhbd_id'])) {
                throw new RuntimeException(sprintf('hhbd has no %s %d', $entity, $document['hhbd_id']));
            }
            return (int) $document['hhbd_id'];
        }
        return $naturalKey();
    }

    /**
     * The row a reference names: a document read earlier in this batch, an hhbd id, an
     * external id or a name.
     *
     * @return int
     */
    private function resolve($ref, $entity)
    {
        if (isset($this->refs[$ref])) {
            list($refEntity, $id) = $this->refs[$ref];
            if ($refEntity !== $entity) {
                throw new RuntimeException(sprintf('%s is an %s, not an %s', $ref, $refEntity, $entity));
            }
            return $id;
        }
        $parts = explode(':', $ref, 3);
        if ('name' === $parts[0]) {
            $name = substr($ref, 5);
            $id = $this->byName($entity, $name);
            if (null === $id) {
                throw new RuntimeException(sprintf('hhbd has no %s named "%s"', $entity, $name));
            }
            return $id;
        }
        if ('hhbd' === $parts[0] && 3 === count($parts)) {
            if ($parts[1] !== $entity || !$this->exists($entity, $parts[2])) {
                throw new RuntimeException(sprintf('hhbd has no %s %s', $entity, $ref));
            }
            return (int) $parts[2];
        }
        if (3 === count($parts) && isset(Model_ExternalId_Api::VOCABULARY[$parts[0]])) {
            $owner = $this->externalIds->findEntity($parts[0], $parts[1], $parts[2]);
            if (null === $owner || $owner['entity_type'] !== $entity) {
                throw new RuntimeException(sprintf('No %s has the id %s', $entity, $ref));
            }
            return $owner['entity_id'];
        }
        throw new RuntimeException(sprintf('%s names no document read before it in the batch', $ref));
    }

    private function exists($entity, $id)
    {
        return !empty($this->_db->fetchAll('SELECT id FROM ' . self::TABLES[$entity] . ' WHERE id = ?', array((int) $id)));
    }

    /** An artist or a label by name, compared under the catalogue's collation (#71) */
    private function byName($entity, $name)
    {
        if ('artist' === $entity) {
            return $this->artistNamed($name);
        }
        if ('label' !== $entity) {
            return null;
        }
        $rows = $this->_db->fetchAll('SELECT id FROM labels WHERE name = ?', array(trim($name)));
        return empty($rows) ? null : (int) $rows[0]['id'];
    }

    /**
     * The one artist of that name, and of that qualifier when one is given (#102). A name two
     * artists share names neither of them: the document has to say which, by an id or a
     * qualifier, so it is refused rather than given the first.
     *
     * @return int|null
     */
    private function artistNamed($name, $disambiguation = null)
    {
        $sql = 'SELECT id, disambiguation FROM artists WHERE name = ?';
        $values = array(trim($name));
        if (null !== $disambiguation) {
            $sql .= ' AND disambiguation = ?';
            $values[] = $disambiguation;
        }
        $rows = $this->_db->fetchAll($sql . ' ORDER BY id', $values);
        if (count($rows) > 1) {
            $which = array();
            foreach ($rows as $row) {
                $which[] = 'hhbd:artist:' . $row['id'] . ('' === $row['disambiguation'] ? '' : ' "' . $row['disambiguation'] . '"');
            }
            throw new RuntimeException(sprintf('%d artists are called "%s" (%s); name one by its id or its qualifier', count($rows), trim($name), implode(', ', $which)));
        }
        return empty($rows) ? null : (int) $rows[0]['id'];
    }

    /** @return array of array(source, kind, value) */
    private function idsOf(array $document)
    {
        $ids = array();
        foreach (isset($document['external_ids']) ? $document['external_ids'] : array() as $name => $value) {
            list($source, $kind) = explode(':', $name, 2);
            $ids[] = array($source, $kind, $value);
        }
        return $ids;
    }

    /**
     * Adds the ids a row does not have yet. Of a kind a row has one of, a second value means the
     * batch and hhbd disagree on what the row is, which a person settles: it is a warning, and
     * hhbd keeps its own.
     */
    private function addIds($entity, $id, array $document)
    {
        $has = array();
        foreach ($this->_db->fetchAll('SELECT source, kind, value FROM external_ids WHERE entity_type = ? AND entity_id = ?', array($entity, $id)) as $row) {
            $has[$row['source'] . ':' . $row['kind']][] = $row['value'];
        }
        foreach ($this->idsOf($document) as list($source, $kind, $value)) {
            $name = $source . ':' . $kind;
            $value = Model_ExternalId_Api::normalise($source, $kind, $value);
            if (in_array($name, self::ONE_PER_ROW, true) && !empty($has[$name]) && !in_array($value, $has[$name], true)) {
                $this->warnings[] = sprintf('%s %d has %s %s, the batch %s; hhbd keeps its own', $entity, $id, $name, implode(', ', $has[$name]), $value);
                continue;
            }
            if ($this->externalIds->add($entity, $id, $source, $kind, $value)) {
                $this->touched['ids'] = true;
            }
        }
    }

    // --- Labels ----------------------------------------------------------------------------

    private function readLabel(array $doc)
    {
        $name = trim($doc['name']);
        $id = $this->match('label', $doc, function () use ($name) {
            return $this->byName('label', $name);
        });
        $facts = array('website' => $this->value($doc, 'website'), 'profile' => $this->value($doc, 'profile'));
        $created = null === $id;
        if ($created) {
            $this->_db->query(
                "INSERT INTO labels (name, urlname, website, profile, logo, addedby, added, status) VALUES (?, ?, ?, ?, '', ?, NOW(), 999)",
                array($name, $this->slug($name, 40), $facts['website'], $facts['profile'], $this->userId)
            );
            $id = (int) $this->_db->lastInsertId();
            $this->touch('core', 'facts', $facts);
        } else {
            $this->fill('labels', $id, $name, 'facts', $facts);
        }
        $this->addIds('label', $id, $doc);
        if (!empty($doc['logo'])) {
            $this->addLogo($id, $doc['logo']);
        }
        return array('label', $id, $created);
    }

    private function addLogo($labelId, array $file)
    {
        $row = $this->_db->fetchAll('SELECT logo FROM labels WHERE id = ?', array($labelId));
        if ('' !== (string) $row[0]['logo']) {
            return;
        }
        $image = $this->image($file);
        $name = $image->getSha256() . '.png';
        $this->stage($image, 'l/' . $name, Model_Import_Image::LOGO_SIZE, 'png');
        $this->update('labels', $labelId, 'logo', array('logo' => $name));
    }

    // --- Artists ---------------------------------------------------------------------------

    private function readArtist(array $doc)
    {
        $name = trim($doc['name']);
        // A namesake the batch tells apart is matched by its name and qualifier together,
        // never by the name alone (#102).
        $disambiguation = trim((string) $this->value($doc, 'disambiguation', ''));
        $id = $this->match('artist', $doc, function () use ($name, $disambiguation) {
            return $this->artistNamed($name, '' === $disambiguation ? null : $disambiguation);
        });
        // An artist with members is a band (#65).
        $type = !empty($doc['members']) ? 'b' : $this->value($doc, 'type', 'x');
        $facts = array(
            'since'   => $this->value($doc, 'active_since'),
            'website' => $this->value($doc, 'website'),
            'profile' => $this->value($doc, 'profile'),
        );
        $created = null === $id;
        if ($created) {
            $this->_db->query(
                "INSERT INTO artists (name, disambiguation, urlname, realname, type, since, website, profile, trivia, addedby, added, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?, NOW(), 999)",
                array($name, $disambiguation, $this->slug(Model_Artist_Container::qualifiedNameOf($name, $disambiguation), 40),
                    $this->value($doc, 'real_name'), $type, $facts['since'], (string) $facts['website'], $facts['profile'], $this->userId)
            );
            $id = (int) $this->_db->lastInsertId();
            $this->touch('core', 'facts', $facts);
        } else {
            // 'x' is the type nobody chose, so it is the one a batch may fill.
            $this->fill('artists', $id, $name, 'core', array(
                'disambiguation' => $disambiguation,
                'realname'       => $this->value($doc, 'real_name'),
                'type'           => 'x' === $type ? null : $type,
            ), array('type' => 'x'));
            $this->fill('artists', $id, $name, 'facts', $facts);
        }
        $this->addIds('artist', $id, $doc);
        // A namesake the batch could not settle goes to a person, on the artist's page (#103).
        if (!empty($doc['review'])) {
            $suggestions = isset($doc['review']['suggestions']) ? $doc['review']['suggestions'] : array();
            $this->review('artist', $id, 'namesake', array('text' => $doc['review']['reason'], 'suggestions' => $suggestions));
        }

        foreach (isset($doc['aliases']) ? $doc['aliases'] : array() as $alias) {
            $this->link('facts', 'altnames_lookup', array('artistid' => $id, 'altname' => trim($alias)));
        }
        foreach (isset($doc['members']) ? $doc['members'] : array() as $member) {
            $this->link('facts', 'band_lookup', array(
                'artistid'  => $this->resolve($member['ref'], 'artist'),
                'bandid'    => $id,
                'insince'   => $this->value($member, 'since'),
                'awaysince' => $this->value($member, 'until'),
            ));
        }
        foreach (isset($doc['cities']) ? $doc['cities'] : array() as $city) {
            $this->link('facts', 'artist_city_lookup', array('cityid' => $this->cityId($city), 'artistid' => $id));
        }
        foreach (isset($doc['photos']) ? $doc['photos'] : array() as $photo) {
            $this->addPhoto($id, $photo);
        }
        return array('artist', $id, $created);
    }

    /** A city by name, made when it is new (#64) */
    private function cityId($name)
    {
        $rows = $this->_db->fetchAll('SELECT id FROM cities WHERE name = ?', array(trim($name)));
        if (!empty($rows)) {
            return (int) $rows[0]['id'];
        }
        $this->_db->query('INSERT INTO cities (name, addedby, added, status) VALUES (?, ?, NOW(), 999)', array(trim($name), $this->userId));
        return (int) $this->_db->lastInsertId();
    }

    private function addPhoto($artistId, array $photo)
    {
        $image = $this->image($photo);
        // Named after the original, so the same photo in a later batch is found again.
        $name = $image->getSha256() . '.jpg';
        if (!empty($this->_db->fetchAll('SELECT id FROM artists_photos WHERE artistid = ? AND filename = ?', array($artistId, $name)))) {
            return;
        }
        $count = $this->_db->fetchAll('SELECT COUNT(*) AS n FROM artists_photos WHERE artistid = ?', array($artistId));
        if ((int) $count[0]['n'] >= self::MAX_PHOTOS) {
            $this->warnings[] = sprintf('artist %d has %d photos already; %s is left out', $artistId, self::MAX_PHOTOS, $photo['path']);
            return;
        }
        $written = $this->stage($image, 'p/' . $name, Model_Import_Image::PHOTO_SIZE);
        $this->images->addArtistPhoto($artistId, array(
            'filename'    => $name,
            'width'       => $written['width'],
            'height'      => $written['height'],
            'sha256'      => $written['sha256'],
            'mime'        => $written['mime'],
            'description' => (string) $this->value($photo, 'description', ''),
            'source'      => (string) $this->value($photo, 'source', ''),
            'sourceurl'   => (string) $this->value($photo, 'source_url', ''),
            'licence'     => $this->value($photo, 'licence'),
            'licence_url' => $this->value($photo, 'licence_url'),
            'credit'      => $this->value($photo, 'credit'),
            // A CC licence asks to say the photo was changed; scaling it down is a change.
            'modified'    => !empty($photo['modified']) || $written['width'] !== $image->getWidth() ? 1 : 0,
            'addedby'     => $this->userId,
        ), !empty($photo['main']));
        $this->touched['photos'] = true;
    }

    // --- Releases --------------------------------------------------------------------------

    private function readRelease(array $doc)
    {
        $credits = array();
        foreach ($doc['artists'] as $artist) {
            $credits[] = array('id' => $this->resolve($artist['ref'], 'artist')) + $artist;
        }
        $main = array_values(array_filter($credits, function ($credit) {
            return 'main' === $credit['role'];
        }));
        if (empty($main)) {
            throw new RuntimeException('A release needs a main artist');
        }
        $title = trim($doc['title']);
        $year = !empty($doc['release_date']) ? (int) substr($doc['release_date'], 0, 4) : null;
        $id = $this->match('album', $doc, function () use ($title, $year, $main) {
            // The first main artist's album of that title, its year one either side at most.
            $rows = $this->_db->fetchAll(
                'SELECT a.id FROM albums a JOIN album_artist_lookup l ON l.albumid = a.id
                  WHERE l.artistid = ? AND a.title = ? AND (? IS NULL OR a.year IS NULL OR ABS(YEAR(a.year) - ?) <= 1)
                  ORDER BY a.id',
                array($main[0]['id'], $title, $year, $year)
            );
            return empty($rows) ? null : (int) $rows[0]['id'];
        });

        $formats = isset($doc['formats']) ? $doc['formats'] : array();
        $catalog = isset($doc['catalog_numbers']) ? $doc['catalog_numbers'] : array();
        $core = array(
            'release_type'           => $this->value($doc, 'release_type', 'album'),
            'labelid'                => !empty($doc['label']) ? $this->resolve($doc['label']['ref'], 'label') : null,
            'self_released'          => !empty($doc['self_released']) ? 1 : 0,
            'year'                   => $this->value($doc, 'release_date'),
            'release_date_precision' => $this->value($doc, 'release_date_precision', 'day'),
            'announced'              => !empty($doc['announced']) ? 1 : 0,
            'legal'                  => (isset($doc['legal']) && false === $doc['legal']) ? 'n' : 'y',
            'epfor'                  => !empty($doc['parent_release']) ? $this->resolve($doc['parent_release']['ref'], 'album') : null,
            'media_cd'               => in_array('cd', $formats, true) ? 1 : null,
            'media_lp'               => in_array('lp', $formats, true) ? 1 : null,
            'media_mc'               => in_array('mc', $formats, true) ? 1 : null,
            'media_digital'          => in_array('digital', $formats, true) ? 1 : 0,
            'catalog_cd'             => $this->value($catalog, 'cd'),
            'catalog_lp'             => $this->value($catalog, 'lp'),
            'catalog_mc'             => $this->value($catalog, 'mc'),
            'catalog_digital'        => $this->value($catalog, 'digital'),
        );
        $facts = array('description' => $this->value($doc, 'description'));

        $created = null === $id;
        if ($created) {
            $columns = array_merge($core, $facts);
            // Unpublished until settlePublication() has seen its tracklist too (#168).
            $this->_db->query(
                'INSERT INTO albums (title, urlname, premier, artistabout, ' . implode(', ', array_keys($columns)) . ", addedby, added, status)
                 VALUES (?, ?, '', ''" . str_repeat(', ?', count($columns)) . ', ?, NOW(), 0)',
                array_merge(array($title, $this->slug($title)), array_values($columns), array($this->userId))
            );
            $id = (int) $this->_db->lastInsertId();
            $this->touch('core', 'facts', $facts);
        } else {
            $this->refineDate($id, $core['year'], $core['release_date_precision']);
            // Only what a person may have left empty: a type, a precision or a legal flag always
            // holds a value, and a default nobody chose cannot be told from a choice.
            $this->fill('albums', $id, $title, 'core', array_intersect_key($core, array_flip(array(
                'labelid', 'year', 'epfor', 'catalog_cd', 'catalog_lp', 'catalog_mc', 'catalog_digital',
            ))));
            $this->fill('albums', $id, $title, 'facts', $facts);
            // A self-release is no label, so it fills in only where hhbd names none (#168).
            if (1 === $core['self_released']) {
                $label = $this->_db->fetchAll('SELECT labelid FROM albums WHERE id = ?', array($id));
                if (null === $label[0]['labelid']) {
                    $this->fill('albums', $id, $title, 'core', array('self_released' => 1), array('self_released' => '0'));
                } else {
                    $this->warnings[] = sprintf('album %d has label %d, the batch says it is self-released; hhbd keeps its label', $id, $label[0]['labelid']);
                }
            }
        }
        $this->addIds('album', $id, $doc);

        foreach ($credits as $credit) {
            $this->link('core', 'album_artist_lookup', array(
                'albumid'     => $id,
                'artistid'    => $credit['id'],
                'role'        => $credit['role'],
                'position'    => $credit['position'],
                'credited_as' => $this->value($credit, 'credited_as'),
            ));
        }
        if (!empty($doc['tracklist'])) {
            $this->addTracklist($id, $doc['tracklist']);
        }
        if (!empty($doc['cover'])) {
            $this->addCover($id, $doc['cover']);
        }
        // What the sources disagreed on, for a person to settle on the album's page (#103).
        foreach (isset($doc['review']) ? $doc['review'] : array() as $doubt) {
            $this->review('album', $id, $doubt['reason'], array_filter(array(
                'text'   => $this->value($doubt, 'note'),
                'values' => $this->value($doubt, 'values'),
            )));
        }
        $this->settlePublication($id, $created);
        return array('album', $id, $created);
    }

    /**
     * A date the album lacks, or one more precise than hhbd's and inside it, goes in with its
     * precision: a day in the month or the year hhbd knows refines it, as an album needs its day
     * to be published (#168). A date that disagrees is left to fill(), which keeps hhbd's.
     */
    private function refineDate($id, $date, $precision)
    {
        if (empty($date)) {
            return;
        }
        $row = $this->_db->fetchAll('SELECT year, release_date_precision FROM albums WHERE id = ?', array($id));
        $has = $row[0]['year'];
        $hasPrecision = $row[0]['release_date_precision'];
        $order = array('year' => 1, 'month' => 2, 'day' => 3);
        if (!empty($has)) {
            if ($order[$precision] <= $order[$hasPrecision]) {
                return;
            }
            $known = 'year' === $hasPrecision ? 4 : 7;
            if (substr($has, 0, $known) !== substr($date, 0, $known)) {
                return;
            }
        }
        $this->update('albums', $id, 'core', array('year' => $date, 'release_date_precision' => $precision));
    }

    /**
     * Shows an album an import made once it has what a visitor is shown, and holds back one that
     * lacks a part, with a review item naming the parts (#168): a label or a self-release, a
     * date to the day, a tracklist. A later batch that brings the rest publishes it and closes
     * the item. An album hhbd had before any import, or one an admin published as it was, keeps
     * its status whatever it lacks.
     */
    private function settlePublication($id, $created)
    {
        $lacks = Model_Album_Api::getInstance()->lacksOf($id);
        $row = $this->_db->fetchAll('SELECT status, addedby FROM albums WHERE id = ?', array($id));
        $published = Model_Album_Api::PUBLISHED === (int) $row[0]['status'];
        $open = null;
        foreach ($this->reviews->openFor('album', $id) as $item) {
            if ('incomplete' === $item->reason) {
                $open = $item;
            }
        }

        if (empty($lacks)) {
            if (!$published) {
                $this->setStatus($id, Model_Album_Api::PUBLISHED, $created);
            }
            if (null !== $open) {
                $this->reviews->close($open->id, Model_Review_Api::COMPLETED, 'An import brought what it lacked.', $this->userId);
                $this->warnings[] = sprintf('album %d has all it lacked now, and is published', $id);
            }
            return;
        }

        $detail = array('text' => 'brak: ' . implode(', ', $lacks));
        if (null !== $open) {
            if ($open->getText() !== $detail['text']) {
                $this->reviews->setDetail($open->id, $detail);
            }
            return;
        }
        $made = $created || (int) $row[0]['addedby'] === (int) $this->userId;
        if (!$made || ($published && $this->reviews->publishedByAdmin($id))) {
            return;
        }
        if ($published) {
            $this->setStatus($id, 0, $created);
        }
        $this->review('album', $id, 'incomplete', $detail);
    }

    /**
     * Publishes or holds back an album, saying the import did it (#63, #168), unless the import
     * has just made it: a new row has not been edited.
     */
    private function setStatus($id, $status, $created)
    {
        if ($created) {
            $this->_db->query('UPDATE albums SET status = ? WHERE id = ?', array($status, $id));
            return;
        }
        $this->_db->query('UPDATE albums SET status = ?, updatedby = ?, updated = NOW() WHERE id = ?', array($status, $this->userId, $id));
        // Not a group of the source's fields, so no provenance; the report says updated.
        $this->touched['status'] = true;
    }

    /** Opens an item for a person to settle (#103), and says so in the report */
    private function review($entity, $id, $reason, array $detail)
    {
        $this->reviews->add($entity, $id, $reason, $detail, $this->runId);
        $this->warnings[] = sprintf(
            '%s %d is for a person to review: %s%s',
            $entity,
            $id,
            isset($detail['text']) ? $detail['text'] : Model_Review_Api::REASONS[$reason]['label'],
            empty($detail['suggestions']) ? '' : ' (hhbd ' . $entity . ' ' . implode(', ', $detail['suggestions']) . ')'
        );
    }

    private function addTracklist($albumId, array $tracks)
    {
        $has = $this->_db->fetchAll('SELECT COUNT(*) AS n FROM album_lookup WHERE albumid = ?', array($albumId));
        if ((int) $has[0]['n'] > 0) {
            // A tracklist hhbd has stays as it is; one that differs needs a person to compare.
            if ((int) $has[0]['n'] !== count($tracks)) {
                $this->warnings[] = sprintf('album %d has %d tracks, the batch %d; its tracklist stays as it is', $albumId, $has[0]['n'], count($tracks));
            }
            return;
        }
        foreach ($tracks as $track) {
            $songId = $this->songFor($track);
            $this->_db->query(
                'INSERT INTO album_lookup (songid, albumid, disc, track, status) VALUES (?, ?, ?, ?, 999)',
                array($songId, $albumId, $track['disc'], $track['position'])
            );
            foreach (isset($track['credits']) ? $track['credits'] : array() as $credit) {
                $this->addSongCredit($songId, $credit);
            }
        }
        $this->touched['tracklist'] = true;
    }

    /** The track's song: the one hhbd has with its recording id or ISRC, or a new one */
    private function songFor(array $track)
    {
        $ids = isset($track['external_ids']) ? $track['external_ids'] : array();
        if (!empty($track['isrc'])) {
            $ids['isrc:isrc'] = $track['isrc'];
        }
        $songId = null;
        foreach (array('musicbrainz:recording', 'isrc:isrc') as $name) {
            if (null === $songId && isset($ids[$name])) {
                list($source, $kind) = explode(':', $name);
                $owner = $this->externalIds->findEntity($source, $kind, $ids[$name]);
                $songId = null !== $owner && 'song' === $owner['entity_type'] ? $owner['entity_id'] : null;
            }
        }
        if (null === $songId) {
            $title = trim($track['title']);
            $this->_db->query(
                "INSERT INTO songs (title, urlname, length, instrumental, lyrics, addedby, added, status) VALUES (?, ?, ?, ?, '', ?, NOW(), 999)",
                array($title, $this->slug($title, 40), $this->value($track, 'length_seconds'), !empty($track['instrumental']) ? 1 : 0, $this->userId)
            );
            $songId = (int) $this->_db->lastInsertId();
        }
        $this->addIds('song', $songId, array('external_ids' => $ids));
        return $songId;
    }

    private function addSongCredit($songId, array $credit)
    {
        $artistId = $this->resolve($credit['ref'], 'artist');
        switch ($credit['role']) {
            case 'performer':
                $this->link('tracklist', 'artist_lookup', array('songid' => $songId, 'artistid' => $artistId));
                break;
            case 'feature':
                // A role by name, added when it is new (#66); none is the role nobody named.
                $role = !empty($credit['feat_type']) ? $this->featTypes->resolve($credit['feat_type']) : Model_FeatType_Api::UNKNOWN;
                $this->link('tracklist', 'feature_lookup', array('songid' => $songId, 'artistid' => $artistId, 'feattype' => $role));
                break;
            case 'producer':
                $this->link('tracklist', 'music_lookup', array('songid' => $songId, 'artistid' => $artistId));
                break;
            case 'scratch':
                $this->link('tracklist', 'scratch_lookup', array('songid' => $songId, 'artistid' => $artistId));
                break;
            case 'remix':
                $this->link('tracklist', 'remix_lookup', array('songid' => $songId, 'artistid' => $artistId, 'name' => $this->value($credit, 'remix_name')));
                break;
        }
    }

    /**
     * Writes a cover's variants from its one original (#96) and describes them in
     * album_covers (#60). An album with a cover keeps it: replacing one is a person's call.
     */
    private function addCover($albumId, array $cover)
    {
        // An album with a cover keeps it, unless every one it shows is a stand-in (#60) and
        // this one is larger: then this one takes its place, and the doubt is settled (#103).
        $has = $this->_db->fetchAll(
            "SELECT MAX(GREATEST(width, height)) AS size, MIN(needs_upgrade) AS standin FROM album_covers WHERE albumid = ? AND main = 'y'",
            array($albumId)
        );
        $replaces = null !== $has[0]['size'];
        if ($replaces && 1 !== (int) $has[0]['standin']) {
            return;
        }
        $image = $this->image($cover);
        if ($replaces) {
            // Larger as the pages would show it: a 500 px original makes a 300 cover, as the
            // stand-in it would replace may have; the same original makes the same.
            $longer = max($image->getWidth(), $image->getHeight());
            $shown = 0;
            foreach (Model_Import_Image::coverVariantsFor($image->getWidth(), $image->getHeight()) as $variant) {
                $shown = max($shown, min($longer, Model_Import_Image::COVER_VARIANTS[$variant]));
            }
            if ($shown <= (int) $has[0]['size']) {
                return;
            }
            $this->_db->query("UPDATE album_covers SET main = 'n' WHERE albumid = ? AND main = 'y'", array($albumId));
            foreach ($this->reviews->openFor('album', $albumId) as $item) {
                if ('cover_placeholder' === $item->reason) {
                    $this->reviews->close($item->id, 'replaced', 'A larger cover came with an import.', $this->userId);
                }
            }
            $this->warnings[] = sprintf('album %d: its stand-in cover is replaced by a larger one', $albumId);
        }
        foreach (Model_Import_Image::coverVariantsFor($image->getWidth(), $image->getHeight()) as $variant) {
            $path = 'a/' . $variant . '/' . $image->getSha256() . '.jpg';
            $written = $this->stage($image, $path, Model_Import_Image::COVER_VARIANTS[$variant]);
            $this->_db->query(
                "INSERT INTO album_covers (albumid, variant, path, width, height, sha256, mime, source, sourceurl, licence, main, needs_upgrade)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'y', ?)",
                array($albumId, $variant, $path, $written['width'], $written['height'], $written['sha256'], $written['mime'],
                    $this->value($cover, 'source', 'import'), $this->value($cover, 'source_url'), $this->value($cover, 'licence'),
                    !empty($cover['needs_upgrade']) ? 1 : 0)
            );
        }
        $this->touched['cover'] = true;
        if (!empty($cover['needs_upgrade'])) {
            $this->review('album', $albumId, 'cover_placeholder', array(
                'text' => sprintf('%s, %d × %d px', $this->value($cover, 'source', 'import'), $image->getWidth(), $image->getHeight()),
            ));
        }
    }

    // --- Images for rows hhbd has ----------------------------------------------------------

    private function readImage(array $doc)
    {
        $wants = array('cover' => 'album', 'photo' => 'artist', 'logo' => 'label');
        $entity = $doc['target']['entity'];
        if ($wants[$doc['role']] !== $entity) {
            throw new RuntimeException(sprintf('A %s belongs to an %s, not to an %s', $doc['role'], $wants[$doc['role']], $entity));
        }
        $id = $this->resolve($doc['target']['ref'], $entity);
        if ('cover' === $doc['role']) {
            $this->addCover($id, $doc['file']);
        } elseif ('photo' === $doc['role']) {
            $this->addPhoto($id, $doc['file']);
        } else {
            $this->addLogo($id, $doc['file']);
        }
        return array($entity, $id, false);
    }

    // --- Writing ---------------------------------------------------------------------------

    /**
     * Fills the columns of a row that are empty, and warns of each that holds another value.
     *
     * @param array $empty a value that counts as empty in a column besides null and ''
     */
    private function fill($table, $id, $name, $group, array $values, array $empty = array())
    {
        $current = $this->_db->fetchAll('SELECT * FROM ' . $table . ' WHERE id = ?', array($id));
        $set = array();
        foreach ($values as $column => $value) {
            if (null === $value || '' === $value) {
                continue;
            }
            $now = $current[0][$column];
            if (null === $now || '' === $now || (isset($empty[$column]) && $empty[$column] === $now)) {
                $set[$column] = $value;
            } elseif ((string) $now !== (string) $value) {
                $this->warnings[] = sprintf('%s "%s": %s is "%s" in hhbd, "%s" in the batch; hhbd keeps its own', $table, $name, $column, $now, $value);
            }
        }
        if (!empty($set)) {
            $this->update($table, $id, $group, $set);
        }
    }

    /** Sets columns of a row, saying the import changed it and when (#63) */
    private function update($table, $id, $group, array $values)
    {
        $set = array();
        foreach (array_keys($values) as $column) {
            $set[] = $column . ' = ?';
        }
        $this->_db->query(
            'UPDATE ' . $table . ' SET ' . implode(', ', $set) . ', updatedby = ?, updated = NOW() WHERE id = ?',
            array_merge(array_values($values), array($this->userId, $id))
        );
        $this->touched[$group] = true;
    }

    /**
     * Adds a row to a link table unless it has one with the same key (#57 gave each a unique
     * key). Any other error still stops the document, which INSERT IGNORE would turn into a
     * warning nobody reads.
     */
    private function link($group, $table, array $values)
    {
        $statement = $this->_db->query(
            'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($values)) . ', status) VALUES (' .
                str_repeat('?, ', count($values)) . '999) ON DUPLICATE KEY UPDATE status = status',
            array_values($values)
        );
        if ($statement->rowCount() > 0) {
            $this->touched[$group] = true;
        }
    }

    /** Marks core changed, and the second group when any of its values is set */
    private function touch($core, $group, array $values)
    {
        $this->touched[$core] = true;
        if (!empty(array_filter($values, function ($value) {
            return null !== $value && '' !== $value;
        }))) {
            $this->touched[$group] = true;
        }
    }

    /**
     * Records where each group of fields the document changed came from (#52): only those, so
     * a group hhbd kept its own values for is not credited to the batch.
     */
    private function recordProvenance($entity, $id, array $document)
    {
        foreach (isset($document['provenance']) ? $document['provenance'] : array() as $group => $from) {
            if (empty($this->touched[$group])) {
                continue;
            }
            $this->provenance->record(
                $this->runId,
                $entity,
                $id,
                $group,
                $from['source'],
                $from['source_ref'],
                isset($from['licence']) ? $from['licence'] : null,
                $from['fetched_at']
            );
        }
    }

    /** The image a document names, checked against what the document says of it */
    private function image(array $file)
    {
        return new Model_Import_Image($this->batchDir . '/' . $file['path'], $file);
    }

    /**
     * Writes a size of an image under a staging name next to where it will live, which the
     * commit turns into the real one; a dry run writes it to its scratch directory.
     *
     * @return array width, height, sha256 and mime of what was written
     */
    private function stage(Model_Import_Image $image, $path, $size, $format = 'jpeg')
    {
        $target = ('apply' === $this->mode ? $this->contentDir : $this->scratchDir) . '/' . $path;
        $staging = $target . '.import-' . $this->runId;
        $written = $image->write($staging, $size, $format);
        $this->staged[$staging] = $target;
        return $written;
    }

    private function moveStaged()
    {
        foreach ($this->staged as $staging => $target) {
            if (!rename($staging, $target)) {
                $this->warnings[] = sprintf('could not move %s into place; make check-images lists it', $target);
            }
        }
        $this->staged = array();
    }

    private function dropStaged()
    {
        foreach (array_keys($this->staged) as $staging) {
            if (is_file($staging)) {
                unlink($staging);
            }
        }
        $this->staged = array();
    }

    private function removeScratch()
    {
        if (null === $this->scratchDir || !is_dir($this->scratchDir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->scratchDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->scratchDir);
    }

    private function urlOf($entity, $id)
    {
        $apis = array('album' => 'Model_Album_Api', 'artist' => 'Model_Artist_Api', 'label' => 'Model_Label_Api');
        try {
            return call_user_func(array($apis[$entity], 'getInstance'))->find($id)->getUrl();
        } catch (Exception $e) {
            $this->warnings[] = sprintf('no page for %s %d: %s', $entity, $id, $e->getMessage());
            return null;
        }
    }

    private function slug($text, $length = null)
    {
        $slug = Jkl_Tools_Url::createUrl($text);
        return null === $length ? $slug : substr($slug, 0, $length);
    }

    private function value(array $from, $key, $default = null)
    {
        return (isset($from[$key]) && '' !== $from[$key]) ? $from[$key] : $default;
    }
}
