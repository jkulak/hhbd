<?php

use PHPUnit\Framework\TestCase;

/**
 * The import contract (#56, app/docs/import.schema.json) on documents of every kind, as
 * hhbd-content's export writes them.
 */
class Model_Import_ContractTest extends TestCase
{
    private static $schema;

    public static function setUpBeforeClass(): void
    {
        self::$schema = Jkl_JsonSchema::fromFile(APPLICATION_PATH . '/../docs/import.schema.json');
    }

    private function file(array $extra = array()): array
    {
        return $extra + array('path' => 'files/a.jpg', 'width' => 600, 'height' => 600, 'sha256' => str_repeat('a', 64), 'mime' => 'image/jpeg');
    }

    private function provenance(): array
    {
        return array('core' => array('source' => 'discogs', 'source_ref' => 'discogs:master:1098710', 'fetched_at' => '2026-10-09T12:00:00Z', 'licence' => 'CC0'));
    }

    public function documentsProvider(): array
    {
        $file = array('path' => 'files/a.jpg', 'width' => 600, 'height' => 600, 'sha256' => str_repeat('a', 64), 'mime' => 'image/jpeg');
        return array(
            'a label' => array(array(
                'kind' => 'label', 'ref' => 'label:asfalt-records', 'name' => 'Asfalt Records', 'website' => null, 'profile' => null,
                'logo' => null, 'hhbd_id' => 1, 'external_ids' => array('discogs:label' => '19808'),
            )),
            'an artist with a photo' => array(array(
                'kind' => 'artist', 'ref' => 'artist:taco-hemingway', 'name' => 'Taco Hemingway', 'type' => 'm', 'real_name' => 'Filip Szcześniak',
                'aliases' => array(), 'members' => array(), 'cities' => array('Warszawa'), 'active_since' => null, 'website' => null, 'profile' => null,
                'hhbd_id' => null, 'external_ids' => array('discogs:artist' => '4320863', 'wikidata:item' => 'Q27983381'),
                'photos' => array($file + array('main' => true, 'description' => null, 'source' => 'commons', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Taco.jpg',
                    'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'credit' => 'Jan Kowalski', 'modified' => true)),
            )),
            'a release' => array(array(
                'kind' => 'release', 'ref' => 'release:discogs:master:1098710', 'title' => 'Marmur',
                'artists' => array(array('ref' => 'artist:taco-hemingway', 'role' => 'main', 'position' => 1, 'credited_as' => 'Taco Hemingway')),
                'release_type' => 'album', 'release_date' => '2016-11-03', 'release_date_precision' => 'day', 'announced' => false,
                'label' => array('ref' => 'label:asfalt-records'), 'catalog_numbers' => array('cd' => 'AR-C148', 'digital' => null),
                'formats' => array('digital', 'cd'), 'legal' => true, 'parent_release' => null, 'description' => null,
                'cover' => $file + array('source' => 'coverartarchive', 'source_url' => null, 'licence' => 'unknown', 'needs_upgrade' => false),
                'tracklist' => array(array('disc' => 1, 'position' => 1, 'title' => 'Wiatr', 'length_seconds' => 192, 'isrc' => null, 'instrumental' => false,
                    'external_ids' => array('musicbrainz:recording' => 'b1a9c0e9-d987-4042-ae91-78d6a3267d69'),
                    'credits' => array(array('ref' => 'artist:rumak', 'role' => 'producer', 'feat_type' => null)))),
                'hhbd_id' => null, 'external_ids' => array('discogs:master' => '1098710', 'barcode:gtin14' => '00190295868383'),
            )),
            'a cover for an album hhbd has' => array(array(
                'kind' => 'image', 'target' => array('entity' => 'album', 'ref' => 'hhbd:album:966'), 'role' => 'cover',
                'file' => $file + array('source' => 'discogs', 'source_url' => null, 'licence' => 'restricted', 'needs_upgrade' => true),
            )),
        );
    }

    /**
     * @dataProvider documentsProvider
     */
    public function testEveryKindTheExportWritesIsValid(array $document): void
    {
        $document['provenance'] = $this->provenance();
        $this->assertSame(array(), self::$schema->validate($document));
    }

    /**
     * @dataProvider mistakesProvider
     */
    public function testAMalformedDocumentIsRefusedWithTheReason(array $document, string $error): void
    {
        $this->assertContains($error, self::$schema->validate($document));
    }

    public function mistakesProvider(): array
    {
        return array(
            'a release without a main artist list' => array(array('kind' => 'release', 'ref' => 'release:x', 'title' => 'X'), '$: artists is missing'),
            'a release with no artists' => array(array('kind' => 'release', 'ref' => 'release:x', 'title' => 'X', 'artists' => array()), '$.artists: fewer than 1 items'),
            'an unknown kind of id' => array(array('kind' => 'label', 'ref' => 'label:x', 'name' => 'X', 'external_ids' => array('spotify:artist' => '1')), '$.external_ids.spotify:artist (name): does not match ^(discogs:(master|release|artist|label)|musicbrainz:(release_group|release|recording|artist|label)|wikidata:item|deezer:(album|artist)|itunes:(collection|artist)|plwiki:pageid|barcode:gtin14|isrc:isrc)$'),
            'a date with zero parts' => array(array('kind' => 'release', 'ref' => 'release:x', 'title' => 'X', 'artists' => array(array('ref' => 'artist:a', 'role' => 'main', 'position' => 1)), 'release_date' => '2016-11-00'), '$.release_date: not a date'),
            'a cover image without its file' => array(array('kind' => 'image', 'target' => array('entity' => 'album', 'ref' => 'hhbd:album:1'), 'role' => 'cover'), '$: file is missing'),
            'a GIF' => array(array('kind' => 'image', 'target' => array('entity' => 'album', 'ref' => 'hhbd:album:1'), 'role' => 'cover', 'file' => array('path' => 'files/a.gif', 'width' => 1, 'height' => 1, 'sha256' => str_repeat('a', 64), 'mime' => 'image/gif')), '$.file.mime: "image/gif" is not one of ["image/jpeg","image/png","image/webp"]'),
        );
    }
}
