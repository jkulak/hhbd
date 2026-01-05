<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests to verify that legacy redirect methods have been removed
 * from Model_Song_Api as part of the URL system refactoring.
 */
class Model_Song_ApiRemovalTest extends TestCase
{
    /**
     * Verify that redirectFromOld method has been removed from Song API class
     * Uses reflection to check without instantiating the class
     */
    public function testRedirectFromOldMethodRemoved(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Api');

        $this->assertFalse(
            $reflectionClass->hasMethod('redirectFromOld'),
            'redirectFromOld method should be removed from Song API'
        );
    }

    /**
     * Verify that redirectById method has been removed from Song API class
     * Uses reflection to check without instantiating the class
     */
    public function testRedirectByIdMethodRemoved(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Api');

        $this->assertFalse(
            $reflectionClass->hasMethod('redirectById'),
            'redirectById method should be removed from Song API'
        );
    }

    /**
     * Verify that find method still exists in Song API class
     * Uses reflection to check without instantiating the class
     */
    public function testFindMethodStillExists(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Api');

        $this->assertTrue(
            $reflectionClass->hasMethod('find'),
            'find method should exist in Song API'
        );
    }

    /**
     * Verify the Song API still has essential methods
     * Uses reflection to check without instantiating the class
     */
    public function testEssentialMethodsStillExist(): void
    {
        $reflectionClass = new ReflectionClass('Model_Song_Api');

        $essentialMethods = [
            'find',
            'getInstance',
            'updateView',
            'saveLyrics',
        ];

        foreach ($essentialMethods as $method) {
            $this->assertTrue(
                $reflectionClass->hasMethod($method),
                "Song API should still have $method method"
            );
        }
    }
}
