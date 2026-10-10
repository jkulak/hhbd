<?php

use PHPUnit\Framework\TestCase;

/**
 * schema.org JSON-LD for search engines (#195): an album with its artists, date, label and
 * tracks; an artist; a song; the path to each; and a script no page text can break out of.
 */
class Jkl_StructuredDataTest extends TestCase
{
    private function album(array $extra = array()): array
    {
        return Jkl_StructuredData::album($extra + array(
            'name' => 'Superextra', 'url' => 'https://hhbd.pl/wdowa-superextra-a535.html',
            'artists' => array(array('Wdowa', 'https://hhbd.pl/wdowa-p12.html')),
            'date' => '2010-06-15', 'precision' => 'day', 'type' => 'album',
            'label' => array('name' => 'Alkopoligamia', 'url' => 'https://hhbd.pl/alkopoligamia-l58.html'),
            'selfReleased' => false, 'image' => 'https://hhbd.pl/content/a/600/x.jpg',
            'tracks' => array(array('name' => 'Pogoda', 'url' => 'https://hhbd.pl/pogoda-s7329.html', 'length' => '03:12'), array('name' => 'Bez długości', 'url' => 'https://hhbd.pl/x-s1.html', 'length' => null)),
        ));
    }

    public function testAnAlbumSaysItsArtistsDateLabelCoverAndTracks(): void
    {
        $album = $this->album();

        $this->assertSame('MusicAlbum', $album['@type']);
        $this->assertSame(array(array('@type' => 'MusicGroup', 'name' => 'Wdowa', 'url' => 'https://hhbd.pl/wdowa-p12.html')), $album['byArtist']);
        $this->assertSame('2010-06-15', $album['datePublished']);
        $this->assertSame('Alkopoligamia', $album['recordLabel']['name']);
        $this->assertSame('https://hhbd.pl/content/a/600/x.jpg', $album['image']);
        $this->assertSame(2, $album['numTracks']);
        $this->assertSame(array('@type' => 'MusicRecording', 'name' => 'Pogoda', 'url' => 'https://hhbd.pl/pogoda-s7329.html', 'duration' => 'PT3M12S'), $album['track']['itemListElement'][0]['item']);
        $this->assertArrayNotHasKey('duration', $album['track']['itemListElement'][1]['item'], 'a track with no length says none');
        $this->assertSame(2, $album['track']['itemListElement'][1]['position']);
    }

    public function testAnAlbumWithoutLabelDateCoverOrTracksLeavesThemOut(): void
    {
        $album = $this->album(array('label' => null, 'date' => null, 'image' => null, 'tracks' => array()));

        foreach (array('recordLabel', 'datePublished', 'image', 'numTracks', 'track') as $key) {
            $this->assertArrayNotHasKey($key, $album);
        }
    }

    /**
     * @dataProvider releaseTypes
     */
    public function testAReleaseTypeIsSchemaOrgs(string $type, string $release, ?string $production): void
    {
        $album = $this->album(array('type' => $type));
        $this->assertSame('https://schema.org/' . $release, $album['albumReleaseType']);
        $this->assertSame(null === $production ? null : 'https://schema.org/' . $production, $album['albumProductionType'] ?? null);
    }

    public static function releaseTypes(): array
    {
        return array(
            'an album'      => array('album', 'AlbumRelease', 'StudioAlbum'),
            'an EP'         => array('ep', 'EPRelease', 'StudioAlbum'),
            'a single'      => array('single', 'SingleRelease', 'StudioAlbum'),
            'a mixtape'     => array('mixtape', 'AlbumRelease', 'MixtapeAlbum'),
            'a compilation' => array('compilation', 'AlbumRelease', 'CompilationAlbum'),
            'another kind'  => array('other', 'AlbumRelease', null),
        );
    }

    /**
     * @dataProvider dates
     */
    public function testADateIsGivenAtThePrecisionHhbdHasIt(?string $date, ?string $precision, ?string $expected): void
    {
        $this->assertSame($expected, Jkl_StructuredData::date($date, $precision));
    }

    public static function dates(): array
    {
        return array(
            'a day'   => array('2016-11-03', 'day', '2016-11-03'),
            'a month' => array('2016-11-01', 'month', '2016-11'),
            'a year'  => array('2016-01-01', 'year', '2016'),
            'none'    => array(null, 'day', null),
            'garbage' => array('soon', 'day', null),
        );
    }

    public function testAnArtistSaysItsOtherNamesPhotoAndAlbums(): void
    {
        $artist = Jkl_StructuredData::artist(array(
            'name' => 'Mes', 'url' => 'https://hhbd.pl/mes-p35.html', 'alternateNames' => array('Piotr Szmidt'),
            'image' => 'https://hhbd.pl/content/p/x.jpg', 'albums' => array(array('Zamach na przeciętność', 'https://hhbd.pl/x-a1.html')),
        ));

        $this->assertSame('MusicGroup', $artist['@type']);
        $this->assertSame(array('Piotr Szmidt'), $artist['alternateName']);
        $this->assertSame(array('@type' => 'MusicAlbum', 'name' => 'Zamach na przeciętność', 'url' => 'https://hhbd.pl/x-a1.html'), $artist['album'][0]);
    }

    public function testASongSaysItsArtistAlbumAndLength(): void
    {
        $song = Jkl_StructuredData::song(array(
            'name' => 'Pogoda', 'url' => 'https://hhbd.pl/pogoda-s7329.html', 'length' => '04:05',
            'artists' => array(array('Wdowa', 'https://hhbd.pl/wdowa-p12.html')),
            'album' => array('name' => 'Superextra', 'url' => 'https://hhbd.pl/wdowa-superextra-a535.html'),
        ));

        $this->assertSame('MusicRecording', $song['@type']);
        $this->assertSame('PT4M5S', $song['duration']);
        $this->assertSame('Superextra', $song['inAlbum']['name']);
        $this->assertSame('Wdowa', $song['byArtist'][0]['name']);
    }

    public function testThePathListsEachStepInOrder(): void
    {
        $path = Jkl_StructuredData::breadcrumbs(array(array('Hhbd.pl', 'https://hhbd.pl/'), array('Albumy', 'https://hhbd.pl/albumy.html')));

        $this->assertSame('BreadcrumbList', $path['@type']);
        $this->assertSame(array('@type' => 'ListItem', 'position' => 2, 'name' => 'Albumy', 'item' => 'https://hhbd.pl/albumy.html'), $path['itemListElement'][1]);
    }

    public function testNoTitleCanCloseTheScriptEarly(): void
    {
        $script = Jkl_StructuredData::script(array($this->album(array('name' => '</script><script>alert(1)</script> & "x"'))));

        $this->assertSame(1, substr_count($script, '</script>'));
        $this->assertStringStartsWith('<script type="application/ld+json">{"@context":"https://schema.org","@graph":[', $script);
        $json = substr($script, strlen('<script type="application/ld+json">'), -strlen('</script>'));
        $this->assertSame('</script><script>alert(1)</script> & "x"', json_decode($json, true)['@graph'][0]['name']);
    }
}
