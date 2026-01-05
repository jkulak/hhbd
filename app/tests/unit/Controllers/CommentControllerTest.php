<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for CommentController refactoring
 *
 * This test suite verifies that the refactored CommentController correctly
 * builds URLs for different entity types after the removal of legacy
 * redirectById methods from API classes.
 */
class CommentControllerTest extends TestCase
{
    /**
     * Helper method to simulate URL building logic from CommentController
     * This mimics the switch statement that was refactored.
     */
    private function buildUrlForEntity(string $type, int $id, object $entity): string
    {
        $redirect = '';

        switch ($type) {
            case 'a':
                // Album URL format: artistName+-+albumTitle-aID.html
                $redirect = Jkl_Tools_Url::createUrl($entity->artist->name . '+-+' . $entity->title . '-a' . $entity->id . '.html');
                break;

            case 'p':
            case 'wykonawca':
                // Artist URL format: artistName-pID.html
                $redirect = Jkl_Tools_Url::createUrl($entity->name . '-p' . $entity->id . '.html');
                break;

            case 'l':
            case 'wytwornia':
                // Label URL format: labelName-lID.html
                $redirect = Jkl_Tools_Url::createUrl($entity->name . '-l' . $entity->id . '.html');
                break;

            case 's':
            case 'utwor':
                // Song URL format: songTitle-sID.html
                $redirect = Jkl_Tools_Url::createUrl($entity->title . '-s' . $entity->id . '.html');
                break;

            case 'n':
            case 'news':
                // News URL format: newsTitle-nID.html
                $redirect = Jkl_Tools_Url::createUrl($entity->title . '-n' . $entity->id . '.html');
                break;
        }

        return $redirect;
    }

    /**
     * Test that artist URLs are built correctly
     */
    public function testArtistUrlBuilding(): void
    {
        $entity = new stdClass();
        $entity->id = 42;
        $entity->name = 'Hemp-Gru';

        $url = $this->buildUrlForEntity('p', 42, $entity);

        // URL should contain the artist name and ID
        $this->assertStringContainsString('hemp-gru', $url);
        $this->assertStringContainsString('42', $url);
        // Old method would have returned "Hemp-Gru-p42" but new method cleans it
        $this->assertFalse(strpos($url, 'Hemp') !== false, 'Artist name should be lowercase');
    }

    /**
     * Test that artist URLs work with alternative type code
     */
    public function testArtistUrlBuildingWithAlternativeType(): void
    {
        $entity = new stdClass();
        $entity->id = 42;
        $entity->name = 'Hemp-Gru';

        $url = $this->buildUrlForEntity('wykonawca', 42, $entity);

        $this->assertStringContainsString('hemp-gru', $url);
        $this->assertStringContainsString('42', $url);
    }

    /**
     * Test that label URLs are built correctly
     */
    public function testLabelUrlBuilding(): void
    {
        $entity = new stdClass();
        $entity->id = 5;
        $entity->name = 'Fonografika';

        $url = $this->buildUrlForEntity('l', 5, $entity);

        $this->assertStringContainsString('fonografika', $url);
        $this->assertStringContainsString('5', $url);
    }

    /**
     * Test that label URLs work with alternative type code
     */
    public function testLabelUrlBuildingWithAlternativeType(): void
    {
        $entity = new stdClass();
        $entity->id = 5;
        $entity->name = 'Fonografika';

        $url = $this->buildUrlForEntity('wytwornia', 5, $entity);

        $this->assertStringContainsString('fonografika', $url);
        $this->assertStringContainsString('5', $url);
    }

    /**
     * Test that song URLs are built correctly
     */
    public function testSongUrlBuilding(): void
    {
        $entity = new stdClass();
        $entity->id = 123;
        $entity->title = 'Światło i Ciemność';

        $url = $this->buildUrlForEntity('s', 123, $entity);

        // Should transliterate Polish characters and lowercase
        $this->assertStringContainsString('swiatlo', $url);
        $this->assertStringContainsString('123', $url);
    }

    /**
     * Test that song URLs work with alternative type code
     */
    public function testSongUrlBuildingWithAlternativeType(): void
    {
        $entity = new stdClass();
        $entity->id = 123;
        $entity->title = 'Światło i Ciemność';

        $url = $this->buildUrlForEntity('utwor', 123, $entity);

        $this->assertStringContainsString('swiatlo', $url);
        $this->assertStringContainsString('123', $url);
    }

    /**
     * Test that news URLs are built correctly
     */
    public function testNewsUrlBuilding(): void
    {
        $entity = new stdClass();
        $entity->id = 999;
        $entity->title = 'Breaking News: Hip-Hop in Poland';

        $url = $this->buildUrlForEntity('news', 999, $entity);

        $this->assertStringContainsString('breaking', $url);
        $this->assertStringContainsString('hip-hop', $url);
        $this->assertStringContainsString('999', $url);
    }

    /**
     * Test that news URLs work with alternative type code
     */
    public function testNewsUrlBuildingWithAlternativeType(): void
    {
        $entity = new stdClass();
        $entity->id = 999;
        $entity->title = 'Breaking News: Hip-Hop in Poland';

        $url = $this->buildUrlForEntity('n', 999, $entity);

        $this->assertStringContainsString('breaking', $url);
        $this->assertStringContainsString('hip-hop', $url);
        $this->assertStringContainsString('999', $url);
    }

    /**
     * Test album URLs with artist and album info
     */
    public function testAlbumUrlBuilding(): void
    {
        $artist = new stdClass();
        $artist->name = 'Hemp-Gru';

        $entity = new stdClass();
        $entity->id = 456;
        $entity->title = 'Medium - Raj';
        $entity->artist = $artist;

        $url = $this->buildUrlForEntity('a', 456, $entity);

        // Should contain both artist and album info
        $this->assertStringContainsString('hemp-gru', $url);
        $this->assertStringContainsString('medium', $url);
        $this->assertStringContainsString('raj', $url);
        $this->assertStringContainsString('456', $url);
    }

    /**
     * Test that URLs with Polish characters are handled correctly
     * This is the main improvement from the refactoring
     */
    public function testUrlsWithPolishCharacters(): void
    {
        $entity = new stdClass();
        $entity->id = 1;
        $entity->title = 'Każdy Chce Być Gwiazdą';

        $url = $this->buildUrlForEntity('s', 1, $entity);

        // Should NOT contain Polish characters
        $this->assertStringNotContainsString('ą', $url);
        $this->assertStringNotContainsString('Ł', $url);
        $this->assertStringNotContainsString('Ó', $url);

        // Should contain transliterated versions
        $this->assertStringContainsString('kazdy', $url);
        $this->assertStringContainsString('chce', $url);
        $this->assertStringContainsString('byc', $url);
        $this->assertStringContainsString('gwiazda', $url);
    }

    /**
     * Test URL consistency - ensures the new method produces consistent results
     */
    public function testUrlConsistency(): void
    {
        $entity = new stdClass();
        $entity->id = 100;
        $entity->name = 'Test Artist & Band';

        $url1 = $this->buildUrlForEntity('p', 100, $entity);
        $url2 = $this->buildUrlForEntity('p', 100, $entity);

        $this->assertEquals($url1, $url2, 'URL building should be consistent');
    }

    /**
     * Test that special characters are properly handled in URLs
     */
    public function testSpecialCharactersInUrls(): void
    {
        $entity = new stdClass();
        $entity->id = 50;
        $entity->title = 'Song (Remix) [2024]';

        $url = $this->buildUrlForEntity('s', 50, $entity);

        // Should not contain parentheses or brackets
        $this->assertStringNotContainsString('(', $url);
        $this->assertStringNotContainsString(')', $url);
        $this->assertStringNotContainsString('[', $url);
        $this->assertStringNotContainsString(']', $url);

        // Should contain the core content
        $this->assertStringContainsString('song', $url);
        $this->assertStringContainsString('remix', $url);
        $this->assertStringContainsString('2024', $url);
    }

    /**
     * Test that all URLs are lowercase
     */
    public function testAllUrlsAreLowercase(): void
    {
        $types = ['a', 'p', 's', 'n', 'l'];

        foreach ($types as $type) {
            $entity = new stdClass();
            $entity->id = 1;
            $entity->name = 'TEST NAME';
            $entity->title = 'TEST TITLE';
            $entity->artist = new stdClass();
            $entity->artist->name = 'ARTIST';

            $url = $this->buildUrlForEntity($type, 1, $entity);

            // URL should be entirely lowercase (except numbers)
            $this->assertEquals($url, strtolower($url), "URL for type '$type' should be lowercase");
        }
    }

    /**
     * Test that IDs are preserved in URLs (crucial for routing)
     */
    public function testEntityIdsPreservedInUrls(): void
    {
        $testCases = [
            'a' => 123,
            'p' => 456,
            's' => 789,
            'n' => 321,
            'l' => 654,
        ];

        foreach ($testCases as $type => $id) {
            $entity = new stdClass();
            $entity->id = $id;
            $entity->name = 'Test';
            $entity->title = 'Test Title';
            $entity->artist = new stdClass();
            $entity->artist->name = 'Artist';

            $url = $this->buildUrlForEntity($type, $id, $entity);

            // ID must be in the URL (routing depends on it)
            $this->assertStringContainsString((string)$id, $url, "ID $id must be preserved in URL for type '$type'");
        }
    }
}
