<?php

use PHPUnit\Framework\TestCase;

/**
 * How a tracklist row becomes a disc, a position and the number a page shows (#59), in the
 * old shape (track = disc * 100 + position) and in the new one (a disc column).
 */
class Model_Song_TracklistTest extends TestCase
{
    /**
     * @dataProvider positionsProvider
     */
    public function testARowSaysItsDiscAndPosition(array $row, array $position): void
    {
        $this->assertSame($position, Model_Song_Api::trackPosition($row));
    }

    public function positionsProvider(): array
    {
        return array(
            'a single disc, the old way' => array(array('track' => '7'), array(1, 7)),
            'the first disc, the old way' => array(array('track' => '101'), array(1, 1)),
            'the fourth disc, the old way' => array(array('track' => '406'), array(4, 6)),
            'a position not known, the old way' => array(array('track' => '0'), array(1, 0)),
            'with a disc column' => array(array('track' => '1', 'disc' => '2'), array(2, 1)),
        );
    }

    public function testATwoDiscAlbumNumbersItsTracksByDisc(): void
    {
        $this->assertSame('1-01', Model_Song_Api::trackLabel(1, 1, true));
        $this->assertSame('2-01', Model_Song_Api::trackLabel(2, 1, true));
        $this->assertSame('2-12', Model_Song_Api::trackLabel(2, 12, true));
    }

    public function testASingleDiscAlbumNumbersItsTracksPlainly(): void
    {
        $this->assertSame('1', Model_Song_Api::trackLabel(1, 1, false));
        $this->assertSame('14', Model_Song_Api::trackLabel(1, 14, false));
    }

    public function testBothShapesOfOneTwoDiscAlbumShowTheSameNumbers(): void
    {
        $old = array(array('track' => '101'), array('track' => '102'), array('track' => '201'));
        $new = array(array('track' => '1', 'disc' => '1'), array('track' => '2', 'disc' => '1'), array('track' => '1', 'disc' => '2'));

        $labels = function (array $rows) {
            return array_map(function ($row) {
                list($disc, $track) = Model_Song_Api::trackPosition($row);
                return Model_Song_Api::trackLabel($disc, $track, true);
            }, $rows);
        };

        $this->assertSame(array('1-01', '1-02', '2-01'), $labels($old));
        $this->assertSame($labels($old), $labels($new));
    }
}
