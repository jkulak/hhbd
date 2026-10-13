<?php

use PHPUnit\Framework\TestCase;

/**
 * How many albums the daily release brings out, and when the queue runs dry (#258).
 * tests/release-test.sh runs the queue and the release against the database.
 */
class Model_Release_ApiTest extends TestCase
{
    public function testADayReleasesFiveToTenAlbumsUnlessToldOtherwise(): void
    {
        $this->assertSame(5, Model_Release_Api::MIN);
        $this->assertSame(10, Model_Release_Api::MAX);
    }

    public function testADaysCountIsDrawnBetweenTheBoundsBothIncluded(): void
    {
        $asked = null;
        $count = Model_Release_Api::howMany(5, 10, function ($min, $max) use (&$asked) {
            $asked = array($min, $max);
            return 7;
        });
        $this->assertSame(7, $count);
        $this->assertSame(array(5, 10), $asked);
    }

    public function testADaysCountStaysWithinItsBoundsOverManyDays(): void
    {
        $seen = array();
        for ($day = 0; $day < 500; $day++) {
            $seen[Model_Release_Api::howMany(5, 10)] = true;
        }
        ksort($seen);
        $this->assertSame(array(5, 6, 7, 8, 9, 10), array_keys($seen));
    }

    public function testEqualBoundsReleaseThatManyEveryDay(): void
    {
        $this->assertSame(3, Model_Release_Api::howMany(3, 3));
    }

    public function testBoundsTheWrongWayRoundOrBelowZeroAreRefused(): void
    {
        foreach (array(array(10, 5), array(-1, 5)) as list($min, $max)) {
            try {
                Model_Release_Api::howMany($min, $max);
                $this->fail(sprintf('%d to %d was taken', $min, $max));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('0 <= MIN <= MAX', $e->getMessage());
            }
        }
    }

    public function testAQueueEmptiesAfterAsManyDaysAsTheAveragePaceTakesTodayIncluded(): void
    {
        // 1125 albums, the years 2018-2026 at 125 each, at 7.5 a day: 150 runs, the first today.
        $this->assertSame('2027-03-11', Model_Release_Api::emptiesOn(1125, 5, 10, '2026-10-13'));
        $this->assertSame('2026-10-13', Model_Release_Api::emptiesOn(1, 5, 10, '2026-10-13'));
        $this->assertSame('2026-10-13', Model_Release_Api::emptiesOn(7, 5, 10, '2026-10-13'));
        $this->assertSame('2026-10-14', Model_Release_Api::emptiesOn(8, 5, 10, '2026-10-13'));
    }

    public function testAnEmptyQueueOrAPaceOfNoneEmptiesOnNoDay(): void
    {
        $this->assertNull(Model_Release_Api::emptiesOn(0, 5, 10, '2026-10-13'));
        $this->assertNull(Model_Release_Api::emptiesOn(12, 0, 0, '2026-10-13'));
    }
}
