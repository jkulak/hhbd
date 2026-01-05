<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Song_Container getUrl() and getAlbumUrl() methods
 * These tests verify URL slug generation without database dependencies
 */
class Model_Song_ContainerTest extends TestCase
{
    /**
     * Test that getUrl() method exists and is public
     */
    public function testGetUrlMethodExists(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');

        $this->assertTrue(
            $reflectionClass->hasMethod('getUrl'),
            'Song Container should have getUrl method'
        );

        $method = $reflectionClass->getMethod('getUrl');
        $this->assertTrue(
            $method->isPublic(),
            'getUrl method should be public'
        );
    }

    /**
     * Test that getAlbumUrl() method exists and is public
     */
    public function testGetAlbumUrlMethodExists(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');

        $this->assertTrue(
            $reflectionClass->hasMethod('getAlbumUrl'),
            'Song Container should have getAlbumUrl method'
        );

        $method = $reflectionClass->getMethod('getAlbumUrl');
        $this->assertTrue(
            $method->isPublic(),
            'getAlbumUrl method should be public'
        );
    }

    /**
     * Test that url() method still exists (for backward compatibility)
     */
    public function testUrlMethodExists(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');

        $this->assertTrue(
            $reflectionClass->hasMethod('url'),
            'Song Container should still have url() method'
        );
    }

    /**
     * Test getUrl() method signature
     */
    public function testGetUrlMethodSignature(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');
        $method = $reflectionClass->getMethod('getUrl');

        // Should have no required parameters
        $this->assertEquals(0, $method->getNumberOfRequiredParameters());
    }

    /**
     * Test getAlbumUrl() method signature
     */
    public function testGetAlbumUrlMethodSignature(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');
        $method = $reflectionClass->getMethod('getAlbumUrl');

        // Should have no required parameters
        $this->assertEquals(0, $method->getNumberOfRequiredParameters());
    }

    /**
     * Test that getUrl() logic handles optional artist
     * This tests the logic by inspecting the source code
     */
    public function testGetUrlLogicHandlesOptionalArtist(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');
        $fileName = $reflectionClass->getFileName();
        $this->assertNotFalse($fileName);

        $fileContent = file_get_contents($fileName);

        // Should check for artist and conditionally include it
        $this->assertStringContainsString('function getUrl()', $fileContent);
        $this->assertStringContainsString('$this->artist->items[0]', $fileContent);
        $this->assertStringContainsString('$this->url()', $fileContent);

        // Should use dash without spaces
        $this->assertStringContainsString("->url . '-'", $fileContent);
    }

    /**
     * Test that getAlbumUrl() logic handles optional artist and album
     */
    public function testGetAlbumUrlLogicHandlesOptionalFields(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');
        $fileName = $reflectionClass->getFileName();
        $this->assertNotFalse($fileName);

        $fileContent = file_get_contents($fileName);

        // Should check for album existence
        $this->assertStringContainsString('function getAlbumUrl()', $fileContent);
        $this->assertStringContainsString('$this->album', $fileContent);

        // Should return empty string if no album
        $this->assertStringContainsString("return '';", $fileContent);
    }

    /**
     * Test that Song methods don't use space-dash-space pattern
     */
    public function testSongUrlMethodsUseHyphenWithoutSpaces(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');
        $fileName = $reflectionClass->getFileName();
        $this->assertNotFalse($fileName);

        $fileContent = file_get_contents($fileName);

        // Check within the getUrl and getAlbumUrl methods
        // Should not have ' - ' pattern in URL concatenation
        preg_match_all('/function (getUrl|getAlbumUrl).*?^    }/ms', $fileContent, $matches);

        foreach ($matches[0] as $methodCode) {
            $this->assertStringNotContainsString(
                "' - '",
                $methodCode,
                'URL methods should not use space-dash-space pattern'
            );
        }
    }
}
