<?php

use PHPUnit\Framework\TestCase;

/**
 * What an admin's edits reach (#115). tests/edit-test.sh and tests/review-test.sh hold the
 * column lists against the schema itself.
 */
class Model_Edit_ApiTest extends TestCase
{
    public function testEveryOperationNamesItsArguments(): void
    {
        foreach (Model_Edit_Api::OPERATIONS as $operation => $arguments) {
            $this->assertNotEmpty($arguments, $operation);
        }
        $this->assertSame(array('from', 'into'), Model_Edit_Api::OPERATIONS['merge-albums']);
        $this->assertSame(array('table', 'id', 'column', 'value'), Model_Edit_Api::OPERATIONS['set']);
    }

    public function testAnArtistMergeMovesABandsMembersAndTheBandsAMemberIsIn(): void
    {
        $this->assertSame(array('artistid', 'bandid'), Model_Edit_Api::ARTIST_COLUMNS['band_lookup']);
    }

    public function testAnAlbumMergeMovesItsTracksCreditsCoversAndRatings(): void
    {
        foreach (array('album_lookup', 'album_artist_lookup', 'album_covers', 'ratings', 'ratings_avg') as $table) {
            $this->assertSame(array('albumid'), Model_Edit_Api::ALBUM_COLUMNS[$table], $table);
        }
    }

    public function testTheTablesThatNameARowByTypeAndIdAreReachedToo(): void
    {
        $this->assertSame(array('com_object_type', 'com_object_id'), Model_Edit_Api::ENTITY_TABLES['hhb_comments']);
        $this->assertSame(array('external_ids', 'import_provenance', 'hhb_comments', 'review_items'), array_keys(Model_Edit_Api::ENTITY_TABLES));
    }

    public function testSetChangesTheCatalogueTablesOnly(): void
    {
        $this->assertSame(array('albums', 'artists', 'labels', 'songs'), Model_Edit_Api::SETTABLE);
    }

    public function testSetTakesARowsColumnsAsOneJsonObjectBesideOneColumnAtATime(): void
    {
        $this->assertSame(array('table', 'id', 'values'), Model_Edit_Api::SET_COLUMNS);
        $this->assertSame(array('id', 'updatedby', 'updated'), Model_Edit_Api::SET_OWN);
    }

    public function testTheJsonFormGivesEachColumnItsValueWithNullForNull(): void
    {
        $this->assertSame(
            array('title' => 'Jestem "Hip Hopem"', 'year' => '2010-06-15', 'media_cd' => 1, 'notes' => null, 'urlname' => ''),
            Model_Edit_Api::columnsOf('{"title": "Jestem \\"Hip Hopem\\"", "year": "2010-06-15", "media_cd": 1, "notes": null, "urlname": ""}')
        );
    }

    public function testTheJsonFormKeepsPolishLettersAndANumberTooLongForAnInteger(): void
    {
        $this->assertSame(
            array('title' => 'Żółć', 'viewed' => '123456789012345678901234567890'),
            Model_Edit_Api::columnsOf('{"title": "\\u017b\\u00f3\\u0142\\u0107", "viewed": 123456789012345678901234567890}')
        );
    }

    /**
     * @dataProvider valuesTheJsonFormRefuses
     */
    public function testTheJsonFormRefusesAnythingButAnObjectOfPlainValues($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Model_Edit_Api::columnsOf($value);
    }

    public static function valuesTheJsonFormRefuses(): array
    {
        return array(
            'no VALUE'      => array(null),
            'not JSON'      => array('title=Superextra'),
            'a list'        => array('["Superextra"]'),
            'a string'      => array('"Superextra"'),
            'an empty one'  => array('{}'),
            'a nested one'  => array('{"title": {"pl": "Superextra"}}'),
            'a list inside' => array('{"title": ["Superextra"]}'),
            'a boolean'     => array('{"legal": true}'),
        );
    }
}
