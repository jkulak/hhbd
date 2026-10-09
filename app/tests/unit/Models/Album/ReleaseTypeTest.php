<?php

use PHPUnit\Framework\TestCase;

/**
 * What an album row says about its release type, media and catalog number (#53).
 */
class Model_Album_ReleaseTypeTest extends TestCase
{
    public function testTheTypeComesFromReleaseTypeOnceTheColumnExists(): void
    {
        $this->assertSame('mixtape', Model_Album_Container::releaseTypeOf(array('release_type' => 'mixtape', 'singiel' => 1)));
    }

    /**
     * @dataProvider beforeTheMigrationProvider
     */
    public function testBeforeTheMigrationAnAlbumMarkedSingielOrEpforIsAnEp(array $row, string $type): void
    {
        $this->assertSame($type, Model_Album_Container::releaseTypeOf($row));
    }

    public function beforeTheMigrationProvider(): array
    {
        return array(
            'singiel' => array(array('singiel' => '1', 'epfor' => null), 'ep'),
            'epfor' => array(array('singiel' => null, 'epfor' => '535'), 'ep'),
            'neither' => array(array('singiel' => '0', 'epfor' => '0'), 'album'),
            'an unknown type, as if the enum grew' => array(array('release_type' => 'opera'), 'album'),
        );
    }

    public function testEveryTypeButAlbumAndOtherHasALabelForThePage(): void
    {
        $unlabelled = array_keys(array_filter(Model_Album_Container::RELEASE_TYPE_LABELS, 'is_null'));

        $this->assertSame(array('album', 'other'), $unlabelled);
        $this->assertSame('EP', Model_Album_Container::RELEASE_TYPE_LABELS['ep']);
        $this->assertSame('beat tape', Model_Album_Container::RELEASE_TYPE_LABELS['beat_tape']);
    }

    public function testMediaAreListedInThePagesOrderWithDigitalLast(): void
    {
        $this->assertSame(
            array('CD', 'LP', 'MC', 'cyfrowo'),
            Model_Album_Container::mediaOf(array('media_mc' => '1', 'media_digital' => '1', 'media_cd' => '1', 'media_lp' => '1'))
        );
    }

    public function testADigitalOnlyReleaseIsListedAsDigitalOnly(): void
    {
        $this->assertSame(array('cyfrowo'), Model_Album_Container::mediaOf(array('media_cd' => '0', 'media_mc' => null, 'media_digital' => '1')));
    }

    public function testAReleaseWithoutACdShowsTheNumberItHas(): void
    {
        $this->assertSame('ALK 001', Model_Album_Container::catalogNumberOf(array('catalog_cd' => 'ALK 001', 'catalog_digital' => 'D-1')));
        $this->assertSame('D-1', Model_Album_Container::catalogNumberOf(array('catalog_cd' => '', 'catalog_digital' => 'D-1')));
        $this->assertNull(Model_Album_Container::catalogNumberOf(array('catalog_cd' => null)));
    }
}
