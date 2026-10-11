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

    public function testAnAdminsDayIsWrittenWholeWithItsPrecision(): void
    {
        $this->assertSame(
            array('year' => '2016-11-03', 'release_date_precision' => 'day', 'announced' => 0),
            Model_Review_Api::completion('2016-11-03', null, false, '2026-10-11')
        );
    }

    public function testADayStillAheadIsAnAnnouncement(): void
    {
        $this->assertSame(1, Model_Review_Api::completion('2027-01-15', null, false, '2026-10-11')['announced']);
    }

    public function testALabelIsNoSelfReleaseAndASelfReleaseHasNoLabel(): void
    {
        $this->assertSame(array('labelid' => 58, 'self_released' => 0), Model_Review_Api::completion('', 58, false, '2026-10-11'));
        $this->assertSame(array('labelid' => null, 'self_released' => 1), Model_Review_Api::completion('', null, true, '2026-10-11'));
    }

    /**
     * @dataProvider refusals
     */
    public function testWhatIsNoCompletionIsRefused(string $date, ?int $label, bool $self): void
    {
        $this->expectException(RuntimeException::class);
        Model_Review_Api::completion($date, $label, $self, '2026-10-11');
    }

    public static function refusals(): array
    {
        return array(
            'a year alone'          => array('2016', null, false),
            'a day that is not one' => array('2016-02-30', null, false),
            'words'                 => array('jesień 2016', null, false),
            'a label and a self-release' => array('', 58, true),
            'nothing at all'        => array('', null, false),
        );
    }
}
