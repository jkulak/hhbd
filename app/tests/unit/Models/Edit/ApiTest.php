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
}
