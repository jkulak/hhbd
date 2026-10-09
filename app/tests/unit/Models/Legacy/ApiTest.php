<?php

use PHPUnit\Framework\TestCase;

/**
 * The addresses before the .html ones (#26): what an old slug that names no single row falls
 * back to, and the kinds the routes send.
 */
class Model_Legacy_ApiTest extends TestCase
{
    public function testAnOldSlugFallsBackToTheSearchForItsWords(): void
    {
        $this->assertSame('/szukaj.html?q=Wzgorze+Ya+Pa+3', Model_Legacy_Api::searchUrl('Wzgorze_Ya-Pa_3'));
    }

    public function testRepeatedAndTrailingSeparatorsMakeNoEmptyWords(): void
    {
        $this->assertSame('/szukaj.html?q=dj+spike', Model_Legacy_Api::searchUrl('_dj__spike-'));
    }

    public function testASlugWithCharactersAUrlCannotCarryIsEncoded(): void
    {
        $this->assertSame('/szukaj.html?q=Ja%C5%BAwa+%26+co', Model_Legacy_Api::searchUrl('Jaźwa_&_co'));
    }

    public function testEveryKindTheRoutesSendHasATable(): void
    {
        $routes = file_get_contents(APPLICATION_PATH . '/configs/routes.xml');
        preg_match_all('#<action>slug</action>\s*<kind>(\w+)</kind>#', $routes, $kinds);

        $this->assertSame(array('artist', 'album', 'label', 'song'), $kinds[1]);
        $this->assertSame(array('artist', 'album', 'label', 'song'), array_keys(Model_Legacy_Api::TABLES));
    }
}
