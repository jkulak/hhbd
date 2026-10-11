<?php

use PHPUnit\Framework\TestCase;

/**
 * The catalogue in figures, on the about page (#218): one sentence, each noun in the form its
 * count takes, the years the albums span.
 */
class Zend_View_Helper_FiguresTest extends TestCase
{
    private function figures(array $extra = array()): array
    {
        return $extra + array('albums' => 1417, 'from' => 1993, 'to' => 2026, 'artists' => 4518, 'songs' => 18956, 'labels' => 246);
    }

    public function testTheSentenceCountsEachKindWithTheThousandsApart(): void
    {
        $this->assertSame(
            "Hhbd.pl opisuje dziś 1\u{00A0}417 albumów wydanych w latach 1993–2026, 4\u{00A0}518 wykonawców, 18\u{00A0}956 utworów i 246 wytwórni.",
            Zend_View_Helper_Figures::describe($this->figures())
        );
    }

    public function testEachNounTakesTheFormItsCountAsks(): void
    {
        $this->assertSame(
            'Hhbd.pl opisuje dziś 1 album wydany w 2016 roku, 1 wykonawcę, 1 utwór i 1 wytwórnię.',
            Zend_View_Helper_Figures::describe(array('albums' => 1, 'from' => 2016, 'to' => 2016, 'artists' => 1, 'songs' => 1, 'labels' => 1))
        );
        $this->assertSame(
            'Hhbd.pl opisuje dziś 3 albumy wydane w latach 2015–2017, 2 wykonawców, 22 utwory i 4 wytwórnie.',
            Zend_View_Helper_Figures::describe(array('albums' => 3, 'from' => 2015, 'to' => 2017, 'artists' => 2, 'songs' => 22, 'labels' => 4))
        );
    }

    public function testNoYearsAreGivenWhenNoAlbumHasOne(): void
    {
        $this->assertSame(
            'Hhbd.pl opisuje dziś 0 albumów, 0 wykonawców, 0 utworów i 0 wytwórni.',
            Zend_View_Helper_Figures::describe(array('albums' => 0, 'from' => null, 'to' => null, 'artists' => 0, 'songs' => 0, 'labels' => 0))
        );
    }
}
