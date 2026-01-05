<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Album_Container getUrl() method
 * These tests verify URL slug generation without database dependencies
 */
class Model_Album_ContainerTest extends TestCase
{
    /**
     * Test that getUrl() method exists and is public
     */
    public function testGetUrlMethodExists(): void
    {
        $reflectionClass = new ReflectionClass('Model_Album_Container');

        $this->assertTrue(
            $reflectionClass->hasMethod('getUrl'),
            'Album Container should have getUrl method'
        );

        $method = $reflectionClass->getMethod('getUrl');
        $this->assertTrue(
            $method->isPublic(),
            'getUrl method should be public'
        );
    }

    /**
     * Test that url property still exists (for backward compatibility)
     * Note: It's set in constructor, not declared as a class property
     */
    public function testUrlPropertyIsSetInConstructor(): void
    {
        $reflectionClass = new ReflectionClass('Model_Album_Container');
        $fileName = $reflectionClass->getFileName();
        $this->assertNotFalse($fileName);

        $fileContent = file_get_contents($fileName);

        // Should set $this->url in constructor
        $this->assertStringContainsString('$this->url =', $fileContent);
        $this->assertStringContainsString('Jkl_Tools_Url::createUrl($this->title)', $fileContent);
    }

    /**
     * Test that artist property exists (required for getUrl)
     * Note: It's set in constructor, not declared as a class property
     */
    public function testArtistPropertyIsSetInConstructor(): void
    {
        $reflectionClass = new ReflectionClass('Model_Album_Container');
        $fileName = $reflectionClass->getFileName();
        $this->assertNotFalse($fileName);

        $fileContent = file_get_contents($fileName);

        // Should set $this->artist in constructor
        $this->assertStringContainsString('$this->artist =', $fileContent);
    }

    /**
     * Test getUrl() method signature
     */
    public function testGetUrlMethodSignature(): void
    {
        $reflectionClass = new ReflectionClass('Model_Album_Container');
        $method = $reflectionClass->getMethod('getUrl');

        // Should have no required parameters
        $this->assertEquals(0, $method->getNumberOfRequiredParameters());

        // Should return a value (not void)
        $returnType = $method->getReturnType();
        if ($returnType !== null) {
            $this->assertNotEquals('void', (string)$returnType);
        }
    }

    /**
     * Test that the method combines artist and album URLs
     * This tests the logic by inspecting the source code
     */
    public function testGetUrlLogicCombinesArtistAndAlbumUrls(): void
    {
        $reflectionClass = new ReflectionClass('Model_Album_Container');
        $method = $reflectionClass->getMethod('getUrl');

        // Get the filename
        $fileName = $reflectionClass->getFileName();
        $this->assertNotFalse($fileName);

        // Read the file and check that getUrl() concatenates artist->url and $this->url
        $fileContent = file_get_contents($fileName);
        $this->assertStringContainsString('function getUrl()', $fileContent);
        $this->assertStringContainsString('$this->artist->url', $fileContent);
        $this->assertStringContainsString('$this->url', $fileContent);

        // Most importantly: should use dash without spaces
        $this->assertStringContainsString("->url . '-' . \$this->url", $fileContent);
        $this->assertStringNotContainsString("' - '", $fileContent, 'Should not use space-dash-space pattern');
    }
}
