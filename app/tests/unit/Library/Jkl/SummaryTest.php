<?php

use PHPUnit\Framework\TestCase;

/**
 * The sentence of facts under an album's and an artist's heading (#219): each part when it is
 * known, in the form Polish asks of it, and a sentence that reads without it.
 */
class Jkl_SummaryTest extends TestCase
{
    private function album(array $extra = array()): string
    {
        return Jkl_Summary::album($extra + array(
            'title' => 'In Hajs We Trust', 'artists' => 'Białas & Lanek', 'type' => 'album', 'announced' => false,
            'date' => '2017-09-05', 'precision' => 'day', 'label' => 'SBM Label', 'selfReleased' => false,
            'tracks' => 12, 'duration' => '28:28',
        ));
    }

    public function testAnAlbumSaysWhoWhenByWhichLabelAndHowManyTracks(): void
    {
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, album wydany 5 września 2017 przez SBM Label: 12 utworów, 28:28.', $this->album());
    }

    public function testAReleaseOfAnotherKindTakesItsNounAndItsGender(): void
    {
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, EP-ka wydana 5 września 2017 przez SBM Label: 12 utworów, 28:28.', $this->album(array('type' => 'ep')));
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, kompilacja wydana 5 września 2017 przez SBM Label: 12 utworów, 28:28.', $this->album(array('type' => 'compilation')));
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, wydawnictwo wydane 5 września 2017 przez SBM Label: 12 utworów, 28:28.', $this->album(array('type' => 'other')));
    }

    /**
     * @dataProvider dates
     */
    public function testADateIsSaidToThePrecisionItIsKnown(?string $date, string $precision, bool $announced, string $expected): void
    {
        $this->assertSame($expected, Jkl_Summary::when($date, $precision, $announced));
    }

    public static function dates(): array
    {
        return array(
            'a day' => array('2017-09-05', 'day', false, '5 września 2017'),
            'a month' => array('2016-05-01', 'month', false, 'w maju 2016'),
            'a year' => array('2016-01-01', 'year', false, 'w 2016 roku'),
            'an announced day' => array('2026-11-12', 'day', true, 'na 12 listopada 2026'),
            'an announced month' => array('2026-12-01', 'month', true, 'na grudzień 2026'),
            'an announced year' => array('2027-01-01', 'year', true, 'na 2027 rok'),
            'a month from before precisions, as a zero day' => array('2004-02-00', 'day', false, 'w lutym 2004'),
            'no date' => array(null, 'day', false, ''),
        );
    }

    public function testAnAnnouncementSaysWhenItIsAnnouncedFor(): void
    {
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, album zapowiedziany na 12 listopada 2026 przez SBM Label.', $this->album(array('announced' => true, 'date' => '2026-11-12', 'tracks' => 0)));
    }

    public function testASelfReleaseSaysSo(): void
    {
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, album wydany 5 września 2017 własnym nakładem: 12 utworów, 28:28.', $this->album(array('label' => null, 'selfReleased' => true)));
    }

    public function testWhatIsNotKnownIsLeftOut(): void
    {
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, album: 1 utwór.', $this->album(array('date' => null, 'label' => null, 'tracks' => 1, 'duration' => '--')));
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, album wydany w 2017 roku.', $this->album(array('precision' => 'year', 'label' => null, 'tracks' => 0)));
        $this->assertSame('Białas & Lanek – „In Hajs We Trust”, album wydany 5 września 2017 przez SBM Label: 3 utwory.', $this->album(array('tracks' => 3, 'duration' => 0)));
    }

    public function testAnArtistSaysWhatItIsItsOtherNamesAndItsAlbums(): void
    {
        $this->assertSame(
            'Mes – wykonawca, znany też jako Dj M-easy, Mestentyp i Ten Typ Mes; na Hhbd.pl 6 albumów z lat 2003–2021.',
            Jkl_Summary::artist(array('name' => 'Mes', 'band' => false, 'otherNames' => array('Dj M-easy', 'Mestentyp', 'Ten Typ Mes'), 'albums' => 6, 'from' => 2003, 'to' => 2021))
        );
    }

    public function testAGroupWithOneAlbumAndNoOtherNames(): void
    {
        $this->assertSame(
            '2cztery7 – skład; na Hhbd.pl 1 album z 2004 roku.',
            Jkl_Summary::artist(array('name' => '2cztery7', 'band' => true, 'otherNames' => array(), 'albums' => 1, 'from' => 2004, 'to' => 2004))
        );
    }

    public function testAnArtistWithoutAlbumsSaysOnlyWhatItIs(): void
    {
        $this->assertSame(
            'Kaz Bałagane – wykonawca, znany też jako Kaz.',
            Jkl_Summary::artist(array('name' => 'Kaz Bałagane', 'band' => false, 'otherNames' => array('Kaz'), 'albums' => 0, 'from' => null, 'to' => null))
        );
    }
}
