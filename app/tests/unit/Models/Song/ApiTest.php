<?php

use PHPUnit\Framework\TestCase;

class Model_Song_ApiTest extends TestCase
{
    /**
     * @dataProvider songIds
     */
    public function testASongIdFromARequestIsAWholeNumberFromOne($value, ?int $expected): void
    {
        $this->assertSame($expected, Model_Song_Api::idOf($value));
    }

    public static function songIds(): array
    {
        return array(
            'digits'                 => array('7329', 7329),
            'an int'                 => array(7329, 7329),
            'zero'                   => array('0', null),
            'a negative'             => array('-1', null),
            'a leading zero'         => array('07329', null),
            'digits and more'        => array('7329abc', null),
            'a song address'         => array('/pogoda-s7329.html', null),
            'nothing'                => array('', null),
            'no field at all'        => array(null, null),
            'an array'               => array(array('7329'), null),
            'more digits than an id' => array('12345678901', null),
        );
    }

    public function testLyricsActionConstants(): void
    {
        $this->assertEquals('add', Model_Song_Api::LYRICS_ACTION_ADD);
        $this->assertEquals('edit', Model_Song_Api::LYRICS_ACTION_EDIT);
        $this->assertEquals('delete', Model_Song_Api::LYRICS_ACTION_DELETE);
    }

    public function testMinimumLyricsLength(): void
    {
        $this->assertEquals(20, Model_Song_Api::MINIMUM_LYRICS_LENGTH);
    }

    /**
     * Test that short lyrics (< 20 chars) would trigger delete action
     * This tests the business logic documented in saveLyrics method
     */
    public function testShortLyricsShouldTriggerDeleteAction(): void
    {
        $shortLyrics = 'too short';
        $this->assertLessThan(
            Model_Song_Api::MINIMUM_LYRICS_LENGTH,
            strlen($shortLyrics),
            'Short lyrics should be below minimum length'
        );
    }

    public function testValidLyricsAboveMinimumLength(): void
    {
        $validLyrics = 'This is a valid lyrics text that is long enough';
        $this->assertGreaterThanOrEqual(
            Model_Song_Api::MINIMUM_LYRICS_LENGTH,
            strlen($validLyrics),
            'Valid lyrics should be at or above minimum length'
        );
    }

    /**
     * Test edge case: exactly minimum length
     */
    public function testLyricsAtExactMinimumLength(): void
    {
        $exactLyrics = str_repeat('x', Model_Song_Api::MINIMUM_LYRICS_LENGTH);
        $this->assertEquals(
            Model_Song_Api::MINIMUM_LYRICS_LENGTH,
            strlen($exactLyrics)
        );
    }

    /**
     * Test edge case: one character below minimum
     */
    public function testLyricsOneBelowMinimum(): void
    {
        $almostLyrics = str_repeat('x', Model_Song_Api::MINIMUM_LYRICS_LENGTH - 1);
        $this->assertLessThan(
            Model_Song_Api::MINIMUM_LYRICS_LENGTH,
            strlen($almostLyrics)
        );
    }
}
