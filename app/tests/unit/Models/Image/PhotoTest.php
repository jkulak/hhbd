<?php

use PHPUnit\Framework\TestCase;

/**
 * A stand-in for Jkl_Db holding artists_photos rows, enough for addArtistPhoto().
 */
class Model_Image_FakeDb
{
    public $rows = array();

    public function fetchAll($query, array $bind = array())
    {
        list($artistId, $main) = $bind;
        return array_values(array_filter($this->rows, function ($row) use ($artistId, $main) {
            return $row['artistid'] === $artistId && $row['main'] === $main;
        }));
    }

    public function query($query, array $bind = array())
    {
        if (0 === strpos($query, 'UPDATE')) {
            list($main, $artistId) = $bind;
            foreach ($this->rows as $i => $row) {
                if ($row['artistid'] === $artistId) {
                    $this->rows[$i]['main'] = $main;
                }
            }
            return;
        }
        $this->rows[] = array('id' => count($this->rows) + 1, 'artistid' => $bind[0], 'main' => $bind[1], 'filename' => $bind[2]);
    }

    public function lastInsertId()
    {
        return (string) count($this->rows);
    }
}

/**
 * Artist photos as a gallery with a credit and a licence each (#61).
 */
class Model_Image_PhotoTest extends TestCase
{
    private function photo(array $params): Model_Image_Container
    {
        return new Model_Image_Container($params + array('url' => '/content/p/x.jpg'));
    }

    public function testACommonsPhotoIsCreditedWithItsAuthorLicenceAndChange(): void
    {
        $photo = $this->photo(array(
            'credit' => 'Jan Kowalski', 'sourceurl' => 'https://commons.wikimedia.org/wiki/File:Mes.jpg',
            'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'modified' => '1',
        ));

        $this->assertSame(
            'Fot. <a href="https://commons.wikimedia.org/wiki/File:Mes.jpg" rel="nofollow">Jan Kowalski</a>, '
            . '<a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="nofollow">CC BY-SA 4.0</a> (zmodyfikowane)',
            $photo->getCaption()
        );
    }

    public function testACaptionWithoutLinksNamesWhatIsKnownEscaped(): void
    {
        $this->assertSame('Fot. Jan &amp; Co', $this->photo(array('credit' => 'Jan & Co'))->getCaption());
        $this->assertSame('Fot. press', $this->photo(array('licence' => 'press'))->getCaption());
    }

    public function testAPhotoWithNeitherAuthorNorLicenceHasNoCaption(): void
    {
        $this->assertNull($this->photo(array('source' => 'hhbd'))->getCaption());
    }

    public function testAnArtistsFirstPhotoBecomesTheMainOne(): void
    {
        $db = new Model_Image_FakeDb();
        $api = new Model_Image_Api($db, array());

        $api->addArtistPhoto(35, array('filename' => 'a.jpg'));
        $api->addArtistPhoto(35, array('filename' => 'b.jpg'));

        $this->assertSame(array('y', 'n'), array_column($db->rows, 'main'));
    }

    public function testANewMainPhotoTakesOverFromTheOldOne(): void
    {
        $db = new Model_Image_FakeDb();
        $api = new Model_Image_Api($db, array());

        $api->addArtistPhoto(35, array('filename' => 'a.jpg'));
        $api->addArtistPhoto(2, array('filename' => 'eldo.jpg'));
        $api->addArtistPhoto(35, array('filename' => 'b.jpg'), true);

        $this->assertSame(array('n', 'y', 'y'), array_column($db->rows, 'main'));
    }

    public function testAPhotoNeedsAnArtistAndAFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Model_Image_Api(new Model_Image_FakeDb(), array()))->addArtistPhoto(0, array('filename' => 'a.jpg'));
    }
}
