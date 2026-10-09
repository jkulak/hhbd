<?php

use PHPUnit\Framework\TestCase;

/**
 * The reasons and their actions (#103), as constants the panel relies on. A merge is
 * Model_Edit_Api's (#115).
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
}
