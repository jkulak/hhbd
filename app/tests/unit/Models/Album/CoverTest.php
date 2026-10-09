<?php

use PHPUnit\Framework\TestCase;

/**
 * Which of an album's cover files a page uses (#60): the album page the largest it needs, the
 * lists the smallest.
 */
class Model_Album_CoverTest extends TestCase
{
    private const PAGE = array('600', 'orig', '300');
    private const LIST = array('75', '300', '600', 'orig');

    private function covers(array $variants): array
    {
        $covers = array();
        foreach ($variants as $variant) {
            $covers[$variant] = array('path' => "a/$variant/x.jpg", 'width' => 1, 'height' => 1);
        }
        return $covers;
    }

    public function testThePageTakesThe600CoverOverTheOriginal(): void
    {
        $this->assertSame('a/600/x.jpg', Model_Album_Container::pickCover($this->covers(array('orig', '600', '300', '75')), self::PAGE)['path']);
    }

    public function testThePageTakesTheOriginalWhenThereIsNo600(): void
    {
        $this->assertSame('a/orig/x.jpg', Model_Album_Container::pickCover($this->covers(array('orig', '75')), self::PAGE)['path']);
    }

    public function testALegacyAlbumShowsIts300CoverAndItsThumbnail(): void
    {
        $covers = $this->covers(array('300', '75'));

        $this->assertSame('a/300/x.jpg', Model_Album_Container::pickCover($covers, self::PAGE)['path']);
        $this->assertSame('a/75/x.jpg', Model_Album_Container::pickCover($covers, self::LIST)['path']);
    }

    public function testAListFallsBackToTheSmallestLargerVariant(): void
    {
        $this->assertSame('a/300/x.jpg', Model_Album_Container::pickCover($this->covers(array('orig', '300')), self::LIST)['path']);
    }

    public function testAnAlbumWithoutCoverRowsHasNone(): void
    {
        $this->assertNull(Model_Album_Container::pickCover(array(), self::PAGE));
    }
}
