<?php

use PHPUnit\Framework\TestCase;

/**
 * When a page has to credit a source (#62), and where the credit and the listen links point.
 */
class Model_Provenance_CreditsTest extends TestCase
{
    private function provenance(array $rows): Model_Provenance_List
    {
        $list = new Model_Provenance_List();
        foreach ($rows as list($source, $licence)) {
            $list->add(new Model_Provenance_Container(array(
                'entity_type' => 'album', 'entity_id' => 1, 'field' => 'cover', 'source' => $source,
                'source_ref' => 'x', 'licence' => $licence, 'fetched' => '2026-10-09 12:00:00', 'run_id' => 1,
            )));
        }
        return $list;
    }

    private function ids(array $rows): Jkl_List
    {
        $list = new Jkl_List();
        foreach ($rows as list($source, $kind, $value)) {
            $list->add(new Model_ExternalId_Container(array(
                'entity_type' => 'album', 'entity_id' => 1, 'source' => $source, 'kind' => $kind, 'value' => $value,
            )));
        }
        return $list;
    }

    public function testDataTakenThroughDiscogssApiIsCredited(): void
    {
        $this->assertTrue($this->provenance(array(array('discogs', null)))->requiresDiscogsCredit());
    }

    public function testDataFromDiscogssCc0DumpIsNot(): void
    {
        $this->assertFalse($this->provenance(array(array('discogs', 'CC0')))->requiresDiscogsCredit());
    }

    public function testDataFromOtherSourcesIsNot(): void
    {
        $this->assertFalse($this->provenance(array(array('musicbrainz', null), array('wikidata', 'CC0')))->requiresDiscogsCredit());
        $this->assertFalse($this->provenance(array())->requiresDiscogsCredit());
    }

    public function testTheCreditLinksTheReleaseBeforeTheMaster(): void
    {
        $ids = $this->ids(array(array('discogs', 'master', '7'), array('discogs', 'release', '9')));

        $this->assertSame('https://www.discogs.com/release/9', Model_ExternalId_Api::pageUrl($ids, 'discogs', array('release', 'master')));
        $this->assertSame('https://www.discogs.com/master/7', Model_ExternalId_Api::pageUrl($ids, 'discogs', array('master', 'release')));
    }

    public function testThereIsNoLinkWithoutAnId(): void
    {
        $this->assertNull(Model_ExternalId_Api::pageUrl($this->ids(array(array('deezer', 'album', '1'))), 'itunes', array('collection')));
    }
}
