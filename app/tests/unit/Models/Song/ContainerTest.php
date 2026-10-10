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

    /**
     * @dataProvider youTubeAddresses
     */
    public function testTheVideoIdComesFromEveryFormOfAddressTheSongsKeep(string $url, ?string $id): void
    {
        $this->assertSame($id, Model_Song_Container::youTubeIdOf($url));
    }

    public static function youTubeAddresses(): array
    {
        return array(
            'the Flash player, as most songs have it' => array('http://www.youtube.com/v/dQw4w9WgXcQ?version=3&f=videos&app=youtube_gdata', 'dQw4w9WgXcQ'),
            'the Flash player with options'           => array('http://www.youtube.com/v/a-B_c1D2e3F?fs=1&amp;hl=pl_PL', 'a-B_c1D2e3F'),
            'an embed address'                        => array('https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'),
            'a watch address'                         => array('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'),
            'a watch address with v= further on'      => array('https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'),
            'a short address'                         => array('https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'),
            'nothing'                                 => array('', null),
            'a channel, not a video'                  => array('http://www.youtube.com/user/UrbanRecTv', null),
            'an id too long'                          => array('https://www.youtube.com/embed/dQw4w9WgXcQx', null),
        );
    }
}
