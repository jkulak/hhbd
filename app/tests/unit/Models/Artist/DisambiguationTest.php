<?php

use PHPUnit\Framework\TestCase;

/**
 * Two artists of one name, told apart by a qualifier (#102): the artist's own page and URL
 * always carry it, a list only when it holds both.
 */
class Model_Artist_DisambiguationTest extends TestCase
{
    private function artist(int $id, string $name, ?string $disambiguation = null): Model_Artist_Container
    {
        return new Model_Artist_Container(array('art_id' => $id, 'name' => $name, 'disambiguation' => $disambiguation));
    }

    public function testAnArtistWithoutAQualifierIsCalledByItsNameEverywhere(): void
    {
        $artist = $this->artist(35, 'Mes');

        $this->assertSame('', $artist->disambiguation);
        $this->assertSame('Mes', $artist->qualifiedName);
        $this->assertSame('Mes', $artist->displayName);
        $this->assertSame('mes', $artist->url);
    }

    public function testAnArtistWithAQualifierHasItInItsPageNameAndSlug(): void
    {
        $artist = $this->artist(64, 'Solar', 'SBM Label');

        $this->assertSame('Solar (SBM Label)', $artist->qualifiedName);
        $this->assertSame('solar-sbm-label', $artist->url);
    }

    public function testAListShowsTheQualifierOnlyForTheArtistsSharingAName(): void
    {
        $list = new Jkl_List();
        $list->add($this->artist(64, 'Solar', 'SBM Label'));
        $list->add($this->artist(65, 'Solar', 'raper z Poznania'));
        $list->add($this->artist(35, 'Mes'));

        Model_Artist_Container::qualifyNamesakes($list);

        $this->assertSame(array('Solar (SBM Label)', 'Solar (raper z Poznania)', 'Mes'), array_column($list->items, 'displayName'));
    }

    public function testAListWithOneOfThemNamesHimPlainly(): void
    {
        $list = new Jkl_List();
        $list->add($this->artist(64, 'Solar', 'SBM Label'));
        $list->add($this->artist(35, 'Mes'));

        Model_Artist_Container::qualifyNamesakes($list);

        $this->assertSame(array('Solar', 'Mes'), array_column($list->items, 'displayName'));
    }

    public function testNamesThatDifferOnlyInCaseAreShared(): void
    {
        $artists = array($this->artist(64, 'Solar', 'SBM Label'), $this->artist(66, 'SOLAR'));

        Model_Artist_Container::qualifyNamesakes($artists);

        $this->assertSame(array('Solar (SBM Label)', 'SOLAR'), array_column($artists, 'displayName'));
    }

    public function testAQualifiedNameIsTheNameAloneWithoutAQualifier(): void
    {
        $this->assertSame('Solar', Model_Artist_Container::qualifiedNameOf('Solar', ''));
        $this->assertSame('Solar', Model_Artist_Container::qualifiedNameOf('Solar', null));
        $this->assertSame('Solar (2016)', Model_Artist_Container::qualifiedNameOf('Solar', '2016'));
    }
}
