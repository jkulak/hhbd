<?php

/**
 * One external id of a catalogue row, as stored in external_ids.
 */
#[\AllowDynamicProperties]
class Model_ExternalId_Container
{
    public $entityType;
    public $entityId;
    public $source;
    public $kind;
    public $value;
    public $added;

    /**
     * Pages a person can open for an id, by source and kind. Barcodes and ISRCs have none.
     */
    public const URLS = array(
        'discogs'     => array(
            'master'  => 'https://www.discogs.com/master/%s',
            'release' => 'https://www.discogs.com/release/%s',
            'artist'  => 'https://www.discogs.com/artist/%s',
            'label'   => 'https://www.discogs.com/label/%s',
        ),
        'musicbrainz' => array(
            'release_group' => 'https://musicbrainz.org/release-group/%s',
            'release'       => 'https://musicbrainz.org/release/%s',
            'recording'     => 'https://musicbrainz.org/recording/%s',
            'artist'        => 'https://musicbrainz.org/artist/%s',
            'label'         => 'https://musicbrainz.org/label/%s',
        ),
        'wikidata'    => array('item' => 'https://www.wikidata.org/wiki/%s'),
        'deezer'      => array(
            'album'  => 'https://www.deezer.com/album/%s',
            'artist' => 'https://www.deezer.com/artist/%s',
        ),
        'itunes'      => array(
            'collection' => 'https://music.apple.com/album/%s',
            'artist'     => 'https://music.apple.com/artist/%s',
        ),
        'plwiki'      => array('pageid' => 'https://pl.wikipedia.org/?curid=%s'),
    );

    public function __construct($params)
    {
        $this->entityType = $params['entity_type'];
        $this->entityId = (int) $params['entity_id'];
        $this->source = $params['source'];
        $this->kind = $params['kind'];
        $this->value = $params['value'];
        $this->added = isset($params['added']) ? $params['added'] : null;
    }

    /**
     * The id as one string, the way an import batch refers to a row: "discogs:master:123".
     */
    public function getRef()
    {
        return $this->source . ':' . $this->kind . ':' . $this->value;
    }

    /**
     * The id's page in its source, or null when it has none.
     */
    public function getUrl()
    {
        if (!isset(self::URLS[$this->source][$this->kind])) {
            return null;
        }
        return sprintf(self::URLS[$this->source][$this->kind], rawurlencode($this->value));
    }
}
