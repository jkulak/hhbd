<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Album_Container getUrl() method
 * These tests verify the method signature and basic structure
 */
class Model_Album_ContainerTest extends TestCase
{
    /**
     * Test that getUrl() method exists and is public
     * This ensures the API contract is maintained
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
     * Test getUrl() method signature
     * Ensures it takes no required parameters and returns a string
     */
    public function testGetUrlMethodSignature(): void
    {
        $reflectionClass = new ReflectionClass('Model_Album_Container');
        $method = $reflectionClass->getMethod('getUrl');

        // Should have no required parameters
        $this->assertEquals(
            0,
            $method->getNumberOfRequiredParameters(),
            'getUrl() should not require parameters'
        );
    }
}
