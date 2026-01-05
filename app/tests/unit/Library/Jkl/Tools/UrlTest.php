<?php

use PHPUnit\Framework\TestCase;

class Jkl_Tools_UrlTest extends TestCase
{
    /**
     * Test URL creation with Polish characters transliteration
     * @dataProvider polishCharacterProvider
     */
    public function testCreateUrlWithPolishCharacters(string $input, string $expected): void
    {
        $result = Jkl_Tools_Url::createUrl($input);
        $this->assertEquals($expected, $result);
    }

    public function polishCharacterProvider(): array
    {
        return [
            'single Polish character ó' => [
                'Żelazo',
                'zelazo'
            ],
            'Polish ł character' => [
                'włosy',
                'wlosy'
            ],
            'Polish ż character' => [
                'życzenia',
                'zyczenia'
            ],
            'Polish ę character' => [
                'pędzle',
                'pedzle'
            ],
            'Polish ą character' => [
                'ławy',
                'lawy'
            ],
            'Polish ć character' => [
                'ćwiczenia',
                'cwiczenia'
            ],
            'Polish ń character' => [
                'końcu',
                'koncu'
            ],
            'Polish ś character' => [
                'śpiew',
                'spiew'
            ],
            'Polish ź character' => [
                'źródło',
                'zrodlo'
            ],
            'mixed Polish characters' => [
                'Światło & CIEMNOŚĆ',
                'swiatlo-ciemnosc'
            ],
            'Hemp-Gru artist' => [
                'Hemp-Gru',
                'hemp-gru'
            ],
            'Medium with dash' => [
                'Medium - Raj',
                'medium-raj'
            ],
        ];
    }

    /**
     * Test URL creation with special characters
     * @dataProvider specialCharacterProvider
     */
    public function testCreateUrlWithSpecialCharacters(string $input, string $expected): void
    {
        $result = Jkl_Tools_Url::createUrl($input);
        $this->assertEquals($expected, $result);
    }

    public function specialCharacterProvider(): array
    {
        return [
            'ampersand' => [
                'Rock & Roll',
                'rock-roll'
            ],
            'comma' => [
                'Smith, John',
                'smith-john'
            ],
            'parentheses' => [
                'Song (Remix)',
                'song-remix'
            ],
            'apostrophe' => [
                "Don't Stop",
                'don-t-stop'
            ],
            'multiple special chars' => [
                'Artist: The Best [2024]',
                'artist-the-best-2024'
            ],
            'slash' => [
                'A/B Testing',
                'a-b-testing'
            ],
            'underscore' => [
                'Test_Value',
                'test-value'
            ],
            'at symbol' => [
                'Band @ Club',
                'band-club'
            ],
        ];
    }

    /**
     * Test URL lowercase conversion
     * @dataProvider caseProvider
     */
    public function testCreateUrlLowercase(string $input, string $expected): void
    {
        $result = Jkl_Tools_Url::createUrl($input);
        $this->assertEquals($expected, $result);
    }

    public function caseProvider(): array
    {
        return [
            'all uppercase' => [
                'UPPERCASE',
                'uppercase'
            ],
            'mixed case' => [
                'MiXeD CaSe',
                'mixed-case'
            ],
            'proper nouns' => [
                'John Smith',
                'john-smith'
            ],
            'acronym' => [
                'USA TOUR',
                'usa-tour'
            ],
        ];
    }

    /**
     * Test dash handling
     * @dataProvider dashHandlingProvider
     */
    public function testCreateUrlDashHandling(string $input, string $expected): void
    {
        $result = Jkl_Tools_Url::createUrl($input);
        $this->assertEquals($expected, $result);
    }

    public function dashHandlingProvider(): array
    {
        return [
            'multiple spaces become single dash' => [
                'Multiple   Spaces',
                'multiple-spaces'
            ],
            'spaces and dashes mixed' => [
                'Rock - and - Roll',
                'rock-and-roll'
            ],
            'consecutive dashes collapsed' => [
                'Test---Value',
                'test-value'
            ],
            'leading dashes removed' => [
                '---test',
                'test'
            ],
            'trailing dashes removed' => [
                'test---',
                'test'
            ],
            'space and special char' => [
                'Song & Artist',
                'song-artist'
            ],
        ];
    }

    /**
     * Test real-world artist/album names
     * @dataProvider realWorldProvider
     */
    public function testCreateUrlRealWorldExamples(string $input, string $expected): void
    {
        $result = Jkl_Tools_Url::createUrl($input);
        $this->assertEquals($expected, $result);
    }

    public function realWorldProvider(): array
    {
        return [
            'Polish hip-hop artist' => [
                'Myslovitz',
                'myslovitz'
            ],
            'album with special chars' => [
                'W Dżungli Betonu',
                'w-dzungli-betonu'
            ],
            'artist with dash' => [
                'O.S.T.R',
                'o-s-t-r'
            ],
            'featuring' => [
                'Artist Feat. Producer',
                'artist-feat-producer'
            ],
            'remix notation' => [
                'Song (Original Mix)',
                'song-original-mix'
            ],
            'numbers and text' => [
                '2Pac West Side',
                '2pac-west-side'
            ],
            'Polish diacritics combo' => [
                'Każdy Chce Być Gwiazdą',
                'kazdy-chce-byc-gwiazda'
            ],
        ];
    }

    /**
     * Test edge cases
     */
    public function testCreateUrlEmptyString(): void
    {
        $result = Jkl_Tools_Url::createUrl('');
        $this->assertEquals('', $result);
    }

    public function testCreateUrlOnlySpaces(): void
    {
        $result = Jkl_Tools_Url::createUrl('   ');
        $this->assertEquals('', $result);
    }

    public function testCreateUrlOnlySpecialChars(): void
    {
        $result = Jkl_Tools_Url::createUrl('!@#$%^&*()');
        $this->assertEquals('', $result);
    }

    public function testCreateUrlNumericOnly(): void
    {
        $result = Jkl_Tools_Url::createUrl('12345');
        $this->assertEquals('12345', $result);
    }

    public function testCreateUrlAlphanumeric(): void
    {
        $result = Jkl_Tools_Url::createUrl('Test123Value456');
        $this->assertEquals('test123value456', $result);
    }

    /**
     * Test that createSafeFilename is an alias for createUrl
     */
    public function testCreateSafeFilenameIsAlias(): void
    {
        $input = 'Żelazo Test & More';
        $createUrlResult = Jkl_Tools_Url::createUrl($input);
        $createSafeFilenameResult = Jkl_Tools_Url::createSafeFilename($input);

        $this->assertEquals($createUrlResult, $createSafeFilenameResult);
    }

    /**
     * Test Unicode/UTF-8 handling
     */
    public function testCreateUrlUnicodeCharacters(): void
    {
        // German characters
        $result = Jkl_Tools_Url::createUrl('Größe');
        $this->assertStringNotContainsString('ö', $result);

        // French characters
        $result = Jkl_Tools_Url::createUrl('Café');
        $this->assertStringNotContainsString('é', $result);

        // Polish characters
        $result = Jkl_Tools_Url::createUrl('Łódź');
        $this->assertStringNotContainsString('ó', $result);
        $this->assertStringNotContainsString('Ł', $result);
    }

    /**
     * Test consistency - same input always produces same output
     */
    public function testCreateUrlConsistency(): void
    {
        $input = 'Hemp-Gru & Friends';

        $result1 = Jkl_Tools_Url::createUrl($input);
        $result2 = Jkl_Tools_Url::createUrl($input);
        $result3 = Jkl_Tools_Url::createUrl($input);

        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
    }

    /**
     * Test idempotency - running createUrl on output should return same result
     */
    public function testCreateUrlIdempotency(): void
    {
        $input = 'Hemp-Gru & Friends';

        $result1 = Jkl_Tools_Url::createUrl($input);
        $result2 = Jkl_Tools_Url::createUrl($result1);

        $this->assertEquals($result1, $result2);
    }
}
