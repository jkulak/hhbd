<?php

/**
 * The ids catalogue rows have in other databases (table external_ids, #51).
 *
 * An import matches on these before anything else, so a batch that runs twice finds the rows it
 * created the first time. A row may carry many ids; an id belongs to at most one row. Values are
 * normalised before they are stored or looked up, so the same id written two ways (a barcode
 * with and without spaces, a MusicBrainz id in upper case) is one value.
 */
class Model_ExternalId_Api extends Jkl_Model_Api
{
    public const ENTITY_TYPES = array('album', 'artist', 'label', 'song');

    /**
     * Which kinds of id each source has. Anything outside this list is refused, so a typo in a
     * batch cannot invent a source nobody will ever look up.
     */
    public const VOCABULARY = array(
        'discogs'     => array('master', 'release', 'artist', 'label'),
        'musicbrainz' => array('release_group', 'release', 'recording', 'artist', 'label'),
        'wikidata'    => array('item'),
        'deezer'      => array('album', 'artist'),
        'itunes'      => array('collection', 'artist'),
        'plwiki'      => array('pageid'),
        'barcode'     => array('gtin14'),
        'isrc'        => array('isrc'),
    );

    private static $_instance;

    /**
     * @return Model_ExternalId_Api
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @param object|null $db anything with fetchAll() and query() taking bound values, like
     *                        Jkl_Db; tests pass a stand-in, everything else the shared connection
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
     * The row an id belongs to, or null when no row has it.
     *
     * @return array|null array('entity_type' => ..., 'entity_id' => ...)
     */
    public function findEntity($source, $kind, $value)
    {
        $value = self::normalise($source, $kind, $value);
        $rows = $this->_db->fetchAll(
            'SELECT entity_type, entity_id FROM external_ids WHERE source = ? AND kind = ? AND value = ?',
            array($source, $kind, $value)
        );
        if (empty($rows)) {
            return null;
        }
        return array('entity_type' => $rows[0]['entity_type'], 'entity_id' => (int) $rows[0]['entity_id']);
    }

    /**
     * Every id a row has, ordered by source, kind and value.
     *
     * @return Jkl_List of Model_ExternalId_Container
     */
    public function getForEntity($entityType, $entityId)
    {
        self::assertEntity($entityType, $entityId);
        $rows = $this->_db->fetchAll(
            'SELECT entity_type, entity_id, source, kind, value, added FROM external_ids
              WHERE entity_type = ? AND entity_id = ? ORDER BY source, kind, value',
            array($entityType, (int) $entityId)
        );
        $list = new Jkl_List();
        foreach ($rows as $row) {
            $list->add(new Model_ExternalId_Container($row));
        }
        return $list;
    }

    /**
     * Records an id for a row.
     *
     * @return bool true when the id was added, false when the row had it already
     * @throws Model_ExternalId_ConflictException when the id belongs to another row
     * @throws InvalidArgumentException for an unknown entity type, source or kind, or a value
     *                                  that is not a valid id of its kind
     */
    public function add($entityType, $entityId, $source, $kind, $value)
    {
        self::assertEntity($entityType, $entityId);
        $value = self::normalise($source, $kind, $value);

        $owner = $this->findEntity($source, $kind, $value);
        if (null !== $owner) {
            if ($owner['entity_type'] === $entityType && $owner['entity_id'] === (int) $entityId) {
                return false;
            }
            throw new Model_ExternalId_ConflictException(sprintf(
                '%s:%s:%s belongs to %s %d, not to %s %d',
                $source,
                $kind,
                $value,
                $owner['entity_type'],
                $owner['entity_id'],
                $entityType,
                $entityId
            ));
        }

        $this->_db->query(
            'INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES (?, ?, ?, ?, ?)',
            array($entityType, (int) $entityId, $source, $kind, $value)
        );
        return true;
    }

    /**
     * The stored form of an id: what it is compared and kept as.
     *
     * @throws InvalidArgumentException for an unknown source or kind, or a malformed value
     */
    public static function normalise($source, $kind, $value)
    {
        if (!isset(self::VOCABULARY[$source]) || !in_array($kind, self::VOCABULARY[$source], true)) {
            throw new InvalidArgumentException(sprintf('Unknown external id %s:%s', $source, $kind));
        }
        $value = trim((string) $value);

        switch ($source) {
            case 'barcode':
                return self::toGtin14($value);

            case 'isrc':
                $isrc = strtoupper(str_replace(array('-', ' '), '', $value));
                if (!preg_match('/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/', $isrc)) {
                    throw new InvalidArgumentException(sprintf('Not an ISRC: "%s"', $value));
                }
                return $isrc;

            case 'musicbrainz':
                $mbid = strtolower($value);
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $mbid)) {
                    throw new InvalidArgumentException(sprintf('Not a MusicBrainz id: "%s"', $value));
                }
                return $mbid;

            case 'wikidata':
                $item = strtoupper($value);
                if (!preg_match('/^Q[1-9][0-9]*$/', $item)) {
                    throw new InvalidArgumentException(sprintf('Not a Wikidata item: "%s"', $value));
                }
                return $item;

            default: // discogs, deezer, itunes, plwiki: positive numbers
                if (!preg_match('/^[1-9][0-9]*$/', $value)) {
                    throw new InvalidArgumentException(sprintf('Not a %s %s id: "%s"', $source, $kind, $value));
                }
                return $value;
        }
    }

    /**
     * A barcode as GTIN-14: digits only, left-padded with zeros, so a UPC-A (12 digits) and the
     * EAN-13 written for the same record ("0 190295 868383" and "190295868383") are one value.
     *
     * @throws InvalidArgumentException unless it holds 8, 12, 13 or 14 digits
     */
    public static function toGtin14($barcode)
    {
        $digits = preg_replace('/[\s-]/', '', (string) $barcode);
        if (!preg_match('/^[0-9]+$/', $digits) || !in_array(strlen($digits), array(8, 12, 13, 14), true)) {
            throw new InvalidArgumentException(sprintf('Not a barcode (GTIN-8, 12, 13 or 14): "%s"', $barcode));
        }
        return str_pad($digits, 14, '0', STR_PAD_LEFT);
    }

    private static function assertEntity($entityType, $entityId)
    {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown entity type "%s"', $entityType));
        }
        if ((int) $entityId < 1 || (string) (int) $entityId !== (string) $entityId) {
            throw new InvalidArgumentException(sprintf('Not an id: "%s"', $entityId));
        }
    }
}
