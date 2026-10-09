<?php

use PHPUnit\Framework\TestCase;

/**
 * The reasons, their actions and the merge's reach (#103), as constants the panel and the
 * merge rely on. The review test checks the merge against the schema itself.
 */
class Model_Review_ApiTest extends TestCase
{
    public function testEveryActionOfEveryReasonHasAResolution(): void
    {
        foreach (Model_Review_Api::REASONS as $reason => $spec) {
            foreach ($spec['actions'] as $action) {
                $this->assertArrayHasKey($action, Model_Review_Api::RESOLUTIONS, "$reason: $action");
            }
        }
    }

    public function testTheReasonsAreTheOnesTheSchemaAndTheImporterUse(): void
    {
        $schema = json_decode(file_get_contents(APPLICATION_PATH . '/../docs/import.schema.json'), true);
        $releaseReasons = $schema['$defs']['release']['properties']['review']['items']['properties']['reason']['enum'];

        $this->assertSame(array(), array_diff($releaseReasons, array_keys(Model_Review_Api::REASONS)));
        $this->assertArrayHasKey('namesake', Model_Review_Api::REASONS);
        $this->assertArrayHasKey('cover_placeholder', Model_Review_Api::REASONS);
    }

    public function testAMergeMovesABandsMembersAndTheBandsAMemberIsIn(): void
    {
        $this->assertSame(array('artistid', 'bandid'), Model_Review_Api::ARTIST_COLUMNS['band_lookup']);
    }

    public function testAMergeMovesWhatNamesAnArtistByTypeAndId(): void
    {
        $this->assertSame(array('com_object_type', 'com_object_id', 'p'), Model_Review_Api::ARTIST_ENTITIES['hhb_comments']);
        foreach (array('external_ids', 'import_provenance', 'review_items') as $table) {
            $this->assertSame('artist', Model_Review_Api::ARTIST_ENTITIES[$table][2], $table);
        }
    }
}
