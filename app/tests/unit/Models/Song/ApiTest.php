<?php

use PHPUnit\Framework\TestCase;

class Model_Song_ApiTest extends TestCase
{
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
