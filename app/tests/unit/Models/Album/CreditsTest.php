<?php

use PHPUnit\Framework\TestCase;

/**
 * Several artists credited on one album (#58): their order, roles and the names a page shows,
 * before migration 0015 adds role, position and credited_as and after.
 */
class Model_Album_CreditsTest extends TestCase
{
    public function testWithoutTheNewColumnsEveryCreditIsAMainOneInArtistIdOrder(): void
    {
        $credits = Model_Album_Api::creditsByAlbum(array(
            array('albumid' => '1', 'artistid' => '2'),
            array('albumid' => '1', 'artistid' => '1'),
            array('albumid' => '535', 'artistid' => '8'),
        ));

        $this->assertSame(array(1, 2), array_column($credits[1], 'artistid'));
        $this->assertSame(array('main', 'main'), array_column($credits[1], 'role'));
        $this->assertSame(array(8), array_column($credits[535], 'artistid'));
    }

    public function testWithTheColumnsMainArtistsComeFirstInTheirPositions(): void
    {
        $credits = Model_Album_Api::creditsByAlbum(array(
            array('albumid' => '7', 'artistid' => '3', 'role' => 'featured', 'position' => '1', 'credited_as' => null),
            array('albumid' => '7', 'artistid' => '9', 'role' => 'main', 'position' => '2', 'credited_as' => 'Taco'),
            array('albumid' => '7', 'artistid' => '5', 'role' => 'main', 'position' => '1', 'credited_as' => ''),
        ));

        $this->assertSame(array(5, 9, 3), array_column($credits[7], 'artistid'));
        $this->assertSame(array(null, 'Taco', null), array_column($credits[7], 'credited_as'));
    }

    public function testAnAlbumIsNamedAfterItsMainArtistsInOrder(): void
    {
        $this->assertSame('Białas & Lanek', Model_Album_Container::artistNamesOf(array(
            array('name' => 'Białas', 'role' => 'main'),
            array('name' => 'Lanek', 'role' => 'main'),
            array('name' => 'Gedz', 'role' => 'featured'),
        )));
    }

    public function testAnAlbumWithOneArtistIsNamedAsBefore(): void
    {
        $this->assertSame('Wdowa', Model_Album_Container::artistNamesOf(array(array('name' => 'Wdowa', 'role' => 'main'))));
    }

    public function testAnAlbumCreditingOnlyFeaturedArtistsNamesThemAll(): void
    {
        $this->assertSame('A & B', Model_Album_Container::artistNamesOf(array(
            array('name' => 'A', 'role' => 'featured'),
            array('name' => 'B', 'role' => 'featured'),
        )));
    }
}
