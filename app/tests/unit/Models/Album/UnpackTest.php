<?php

use PHPUnit\Framework\TestCase;

/**
 * An album's row with its credits, covers and label in JSON columns (#255), taken apart into what
 * getCredits(), getCovers() and the artists' and the label's own rows gave before, when each was
 * a query of its own. PostgreSQL's json_build_array() gives every value as text, or null.
 */
class Model_Album_UnpackTest extends TestCase
{
    private function row(array $columns): array
    {
        return array_merge(array('alb_id' => '535', 'title' => 'Superextra', 'lab_id' => null,
            'credits_json' => null, 'covers_json' => null, 'label_json' => null), $columns);
    }

    public function testTheCreditsComeOutInTheOrderCreditsByAlbumGivesThem(): void
    {
        list($row) = Model_Album_Api::unpack($this->row(array('credits_json' => json_encode(array(
            array('3', 'featured', '1', null, 'Gedz', '', null, null, '7'),
            array('9', 'main', '2', 'Taco', 'Taco Hemingway', '', null, null, '40'),
            array('5', 'main', '1', '', 'Wdowa', '', '2001-01-01', null, '12'),
        )))));

        $this->assertSame(array(5, 9, 3), array_column($row['credits'], 'artistid'));
        $this->assertSame(array('main', 'main', 'featured'), array_column($row['credits'], 'role'));
        $this->assertSame(array(null, 'Taco', null), array_column($row['credits'], 'credited_as'));
    }

    public function testEachCreditedArtistComesOutAsTheRowAContainerReads(): void
    {
        list(, $artists) = Model_Album_Api::unpack($this->row(array('credits_json' => json_encode(array(
            array('35', 'main', '1', null, 'Mes', '', '1998-01-01', null, '120'),
        )))));

        $this->assertSame(array(array('art_id' => '35', 'name' => 'Mes', 'disambiguation' => '',
            'since' => '1998-01-01', 'till' => null, 'viewed' => '120')), $artists);
    }

    public function testOfSeveralCoversOfAVariantTheLastOneWins(): void
    {
        list($row) = Model_Album_Api::unpack($this->row(array('covers_json' => json_encode(array(
            array('main', 'a/1.jpg', '600', '600'),
            array('main', 'a/2.jpg', '1200', '1180'),
            array('thumb', 'a/t.jpg', '100', '100'),
        )))));

        $this->assertSame(array(
            'main' => array('path' => 'a/2.jpg', 'width' => 1200, 'height' => 1180),
            'thumb' => array('path' => 'a/t.jpg', 'width' => 100, 'height' => 100),
        ), $row['covers']);
    }

    public function testTheLabelComesOutAsItsOwnRow(): void
    {
        list(, , $label) = Model_Album_Api::unpack($this->row(array('lab_id' => '58', 'label_json' => json_encode(
            array('Alkopoligamia', 'https://alkopoligamia.com', null, null, 'Warszawa', 'l/58.png', '2008-11-02 09:30:00', null)
        ))));

        $this->assertSame('58', $label['lab_id']);
        $this->assertSame('Alkopoligamia', $label['name']);
        $this->assertSame('l/58.png', $label['logo']);
        $this->assertSame('2008-11-02 09:30:00', $label['added']);
    }

    public function testARowWithNoneOfThemHasNoCreditsNoCoversAndNoLabel(): void
    {
        list($row, $artists, $label) = Model_Album_Api::unpack($this->row(array()));

        $this->assertArrayNotHasKey('credits', $row);
        $this->assertArrayNotHasKey('covers', $row);
        $this->assertSame(array(), $artists);
        $this->assertNull($label);
    }

    public function testTheJsonColumnsAreGoneFromTheRow(): void
    {
        list($row) = Model_Album_Api::unpack($this->row(array('lab_id' => '58', 'label_json' => '["A", null, null, null, null, null, null, null]')));

        $this->assertSame(array('alb_id' => '535', 'title' => 'Superextra', 'lab_id' => '58'), $row);
    }
}
