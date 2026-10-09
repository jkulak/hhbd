<?php

use PHPUnit\Framework\TestCase;

/**
 * A stand-in for Jkl_Db: answers lookups from an in-memory external_ids table and records every
 * statement with its bound values.
 */
class Model_ExternalId_FakeDb
{
    public $rows = array();
    public $statements = array();

    public function fetchAll($query, array $bind = array())
    {
        $this->statements[] = array($query, $bind);
        if (strpos($query, 'WHERE source = ?') !== false) {
            list($source, $kind, $value) = $bind;
            return array_values(array_filter($this->rows, function ($row) use ($source, $kind, $value) {
                return $row['source'] === $source && $row['kind'] === $kind && $row['value'] === $value;
            }));
        }
        list($entityType, $entityId) = $bind;
        $rows = array_values(array_filter($this->rows, function ($row) use ($entityType, $entityId) {
            return $row['entity_type'] === $entityType && $row['entity_id'] === $entityId;
        }));
        // Sorted only when the query asks for it, as the database would.
        if (strpos($query, 'ORDER BY source, kind, value') !== false) {
            usort($rows, function ($a, $b) {
                return strcmp($a['source'] . ':' . $a['kind'] . ':' . $a['value'], $b['source'] . ':' . $b['kind'] . ':' . $b['value']);
            });
        }
        return $rows;
    }

    public function query($query, array $bind = array())
    {
        $this->statements[] = array($query, $bind);
        list($entityType, $entityId, $source, $kind, $value) = $bind;
        $this->rows[] = array(
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'source' => $source,
            'kind' => $kind,
            'value' => $value,
            'added' => '2026-10-09 12:00:00',
        );
    }
}

class Model_ExternalId_ApiTest extends TestCase
{
    private $db;
    private $api;

    protected function setUp(): void
    {
        $this->db = new Model_ExternalId_FakeDb();
        $this->api = new Model_ExternalId_Api($this->db);
    }

    public function testAnAddedIdFindsItsRow(): void
    {
        $this->assertTrue($this->api->add('album', 535, 'discogs', 'master', '1234567'));

        $this->assertSame(
            array('entity_type' => 'album', 'entity_id' => 535),
            $this->api->findEntity('discogs', 'master', '1234567')
        );
    }

    public function testAnIdNobodyHasFindsNothing(): void
    {
        $this->assertNull($this->api->findEntity('discogs', 'master', '1'));
    }

    public function testAddingAnIdTheRowAlreadyHasChangesNothing(): void
    {
        $this->api->add('artist', 35, 'wikidata', 'item', 'Q9346013');

        $this->assertFalse($this->api->add('artist', 35, 'wikidata', 'item', 'q9346013'));
        $this->assertCount(1, $this->db->rows);
    }

    public function testAnIdThatBelongsToAnotherRowIsRefused(): void
    {
        $this->api->add('album', 535, 'discogs', 'release', '987');

        $this->expectException(Model_ExternalId_ConflictException::class);
        $this->expectExceptionMessage('discogs:release:987 belongs to album 535, not to album 536');
        $this->api->add('album', 536, 'discogs', 'release', '987');
    }

    public function testARowListsEveryIdItHasInOrder(): void
    {
        $this->api->add('album', 535, 'musicbrainz', 'release_group', 'B1A9C0E9-D987-4042-AE91-78D6A3267D69');
        $this->api->add('album', 535, 'barcode', 'gtin14', '0 190295 868383');
        $this->api->add('album', 536, 'discogs', 'master', '5');

        $ids = $this->api->getForEntity('album', 535)->items;

        $this->assertCount(2, $ids);
        $this->assertSame('barcode:gtin14:00190295868383', $ids[0]->getRef());
        $this->assertSame('musicbrainz:release_group:b1a9c0e9-d987-4042-ae91-78d6a3267d69', $ids[1]->getRef());
    }

    public function testValuesReachTheDatabaseAsBoundParametersNotInTheQuery(): void
    {
        $this->api->add('label', 58, 'discogs', 'label', '271903');

        foreach ($this->db->statements as list($query, $bind)) {
            $this->assertStringNotContainsString('271903', $query);
            $this->assertContains('271903', $bind);
        }
    }

    /**
     * @dataProvider barcodeProvider
     */
    public function testABarcodeIsStoredAsGtin14(string $written, string $stored): void
    {
        $this->assertSame($stored, Model_ExternalId_Api::normalise('barcode', 'gtin14', $written));
    }

    public function barcodeProvider(): array
    {
        return array(
            'EAN-13 written by Discogs with spaces' => array('0 190295 868383', '00190295868383'),
            'the same record as a UPC-A from MusicBrainz' => array('190295868383', '00190295868383'),
            'EAN-13 with dashes' => array('590-1234-123457', '05901234123457'),
            'EAN-8' => array('96385074', '00000096385074'),
            'already GTIN-14' => array('10012345678902', '10012345678902'),
        );
    }

    /**
     * @dataProvider invalidProvider
     */
    public function testAMalformedOrUnknownIdIsRefused(string $source, string $kind, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Model_ExternalId_Api::normalise($source, $kind, $value);
    }

    public function invalidProvider(): array
    {
        return array(
            'a barcode of the wrong length' => array('barcode', 'gtin14', '12345'),
            'a barcode with letters' => array('barcode', 'gtin14', '59012341234AB'),
            'a Discogs id that is not a number' => array('discogs', 'release', 'r123'),
            'a Discogs id of zero' => array('discogs', 'master', '0'),
            'a MusicBrainz id that is not a UUID' => array('musicbrainz', 'artist', 'taco-hemingway'),
            'a Wikidata property instead of an item' => array('wikidata', 'item', 'P19'),
            'an ISRC one character short' => array('isrc', 'isrc', 'PLA23160000'),
            'a source nobody looks up' => array('spotify', 'album', '123'),
            'a kind the source does not have' => array('discogs', 'recording', '123'),
        );
    }

    public function testAnIsrcLosesItsDashesAndIsUpperCase(): void
    {
        $this->assertSame('PLA231600001', Model_ExternalId_Api::normalise('isrc', 'isrc', 'pl-a23-16-00001'));
    }

    public function testAnUnknownEntityTypeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->api->add('concert', 1, 'discogs', 'master', '1');
    }

    public function testANonPositiveEntityIdIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->api->getForEntity('album', 0);
    }

    /**
     * @dataProvider urlProvider
     */
    public function testAnIdLinksToItsPageWhereItHasOne(string $source, string $kind, string $value, ?string $url): void
    {
        $id = new Model_ExternalId_Container(array(
            'entity_type' => 'album',
            'entity_id' => 1,
            'source' => $source,
            'kind' => $kind,
            'value' => $value,
        ));
        $this->assertSame($url, $id->getUrl());
    }

    public function urlProvider(): array
    {
        return array(
            'Discogs master' => array('discogs', 'master', '1234', 'https://www.discogs.com/master/1234'),
            'MusicBrainz release group' => array('musicbrainz', 'release_group', 'b1a9c0e9-d987-4042-ae91-78d6a3267d69', 'https://musicbrainz.org/release-group/b1a9c0e9-d987-4042-ae91-78d6a3267d69'),
            'Wikidata item' => array('wikidata', 'item', 'Q42', 'https://www.wikidata.org/wiki/Q42'),
            'Polish Wikipedia page' => array('plwiki', 'pageid', '12345', 'https://pl.wikipedia.org/?curid=12345'),
            'a barcode has no page' => array('barcode', 'gtin14', '00190295868383', null),
            'an ISRC has no page' => array('isrc', 'isrc', 'PLA231600001', null),
        );
    }
}
