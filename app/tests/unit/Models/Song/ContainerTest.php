<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Song_Container getUrl() and getAlbumUrl() methods
 * These tests verify the method signatures and API contracts
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
            'Song Container should still have url() method for backward compatibility'
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
        $this->assertEquals(
            0,
            $method->getNumberOfRequiredParameters(),
            'getUrl() should not require parameters'
        );
    }

    /**
     * Test getAlbumUrl() method signature
     */
    public function testGetAlbumUrlMethodSignature(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Container');
        $method = $reflectionClass->getMethod('getAlbumUrl');

        // Should have no required parameters
        $this->assertEquals(
            0,
            $method->getNumberOfRequiredParameters(),
            'getAlbumUrl() should not require parameters'
        );
    }
}
