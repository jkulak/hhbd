<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests to verify that legacy redirect methods have been removed
 * from Model_Artist_Api as part of the URL system refactoring.
 */
class Model_Artist_ApiTest extends TestCase
{
    /**
     * Verify that redirectFromOld method has been removed from Artist API class
     * Uses reflection to check without instantiating the class
     */
    public function testRedirectFromOldMethodRemoved(): void
    {
        $reflectionClass = new ReflectionClass('Model_Artist_Api');

        $this->assertFalse(
            $reflectionClass->hasMethod('redirectFromOld'),
            'redirectFromOld method should be removed from Artist API'
        );
    }

    /**
     * Verify that redirectById method has been removed from Artist API class
     * Uses reflection to check without instantiating the class
     */
    public function testRedirectByIdMethodRemoved(): void
    {
        $reflectionClass = new ReflectionClass('Model_Artist_Api');

        $this->assertFalse(
            $reflectionClass->hasMethod('redirectById'),
            'redirectById method should be removed from Artist API'
        );
    }

    /**
     * Verify that find method still exists in Artist API class
     * Uses reflection to check without instantiating the class
     */
    public function testFindMethodStillExists(): void
    {
        $reflectionClass = new ReflectionClass('Model_Artist_Api');

        $this->assertTrue(
            $reflectionClass->hasMethod('find'),
            'find method should exist in Artist API'
        );
    }

    /**
     * Verify the Artist API still has essential methods
     * Uses reflection to check without instantiating the class
     */
    public function testEssentialMethodsStillExist(): void
    {
        $reflectionClass = new ReflectionClass('Model_Artist_Api');

        $essentialMethods = [
            'find',
            'getList',
            'getInstance',
            'updateView',
        ];

        foreach ($essentialMethods as $method) {
            $this->assertTrue(
                $reflectionClass->hasMethod($method),
                "Artist API should still have $method method"
            );
        }
    }
}
