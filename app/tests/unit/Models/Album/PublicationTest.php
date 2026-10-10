<?php

use PHPUnit\Framework\TestCase;

/**
 * What an imported album needs before a visitor is shown it (#168): a label or a self-release,
 * a release date to the day, a tracklist.
 */
class Model_Album_PublicationTest extends TestCase
{
    public function testACompleteAlbumLacksNothing(): void
    {
        $this->assertSame(array(), Model_Album_Api::lacks(58, false, '2010-06-15', 'day', 12));
    }

    public function testASelfReleaseNeedsNoLabel(): void
    {
        $this->assertSame(array(), Model_Album_Api::lacks(null, true, '2016-03-18', 'day', 1));
    }

    public function testAnAlbumWithNoLabelThatIsNoSelfReleaseLacksALabel(): void
    {
        $this->assertSame(array('wytwórnia'), Model_Album_Api::lacks(null, false, '2016-03-18', 'day', 10));
    }

    /**
     * @dataProvider datesShortOfADay
     */
    public function testADateShortOfTheDayLacksTheDate(?string $date, ?string $precision): void
    {
        $this->assertSame(array('data dzienna'), Model_Album_Api::lacks(58, false, $date, $precision, 10));
    }

    public static function datesShortOfADay(): array
    {
        return array(
            'a year'   => array('2016-01-01', 'year'),
            'a month'  => array('2016-11-01', 'month'),
            'no date'  => array(null, 'day'),
        );
    }

    public function testAnAlbumWithoutTracksLacksATracklist(): void
    {
        $this->assertSame(array('tracklista'), Model_Album_Api::lacks(58, false, '2016-03-18', 'day', 0));
    }

    public function testEveryPartItLacksIsNamedInTheOrderTheAdminReadsThem(): void
    {
        $this->assertSame(array('wytwórnia', 'data dzienna', 'tracklista'), Model_Album_Api::lacks(null, false, null, null, 0));
    }

    public function testTrackLengthsAreNoPartOfIt(): void
    {
        // The count is all it asks of a tracklist; a track with no length counts as one.
        $this->assertSame(array(), Model_Album_Api::lacks(58, false, '2016-03-18', 'day', '3'));
    }
}
