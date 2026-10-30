<?php

/**
 * schema.org JSON-LD for an album's, an artist's, a song's or a label's page (#195, #288): what
 * the page is, and the path to it, from the containers the page shows, at absolute addresses; and for the home
 * and about pages, what the site is and who runs it (#218). Jkl_StructuredData builds the
 * JSON; a view prints $this->StructuredData()->album($this->album).
 */
class Zend_View_Helper_StructuredData extends Zend_View_Helper_Abstract
{
    /** What hhbd.pl is, in one sentence, as the footer of every page tells it too */
    public const DESCRIPTION = 'Encyklopedia polskiego hip-hopu prowadzona od 2005 roku: albumy z tracklistami, datami premier i wytwórniami, wykonawcy i składy z dyskografiami, utwory z tekstami i twórcami, wytwórnie i aktualności.';

    public function StructuredData()
    {
        return $this;
    }

    public function album($album)
    {
        $artists = $this->mainArtists($album);
        $tracks = array();
        foreach (!empty($album->tracklist->items) ? $album->tracklist->items : array() as $song) {
            $tracks[] = array('name' => $song->title, 'url' => $this->absolute($song->getUrl()), 'length' => $song->duration);
        }
        $url = $this->absolute($album->getUrl());
        $trail = array(array('Hhbd.pl', $this->absolute('/')), array('Albumy', $this->absolute('/albumy.html')));
        if (!empty($album->artist)) {
            $trail[] = array($album->artist->qualifiedName, $this->absolute($album->artist->getUrl()));
        }
        $trail[] = array($album->title, $url);
        return Jkl_StructuredData::script(array(
            Jkl_StructuredData::album(array(
                'name'         => $album->title,
                'url'          => $url,
                'artists'      => $artists,
                'date'         => $album->releaseDate,
                'precision'    => $album->releaseDatePrecision,
                'type'         => $album->releaseType,
                'label'        => !empty($album->label) ? array('name' => $album->label->name, 'url' => $this->absolute($album->label->getUrl())) : null,
                'selfReleased' => $album->selfReleased,
                'image'        => $this->image($album->cover),
                'tracks'       => $tracks,
            )),
            Jkl_StructuredData::breadcrumbs($trail),
        ));
    }

    public function artist($artist)
    {
        $albums = array();
        foreach (!empty($artist->albums->items) ? $artist->albums->items : array() as $album) {
            $albums[] = array($album->title, $this->absolute($album->getUrl()));
        }
        $photo = !empty($artist->photos->items) ? $artist->photos->items[0]->url : null;
        $url = $this->absolute($artist->getUrl());
        return Jkl_StructuredData::script(array(
            Jkl_StructuredData::artist(array(
                'name'           => $artist->qualifiedName,
                'url'            => $url,
                'alternateNames' => !empty($artist->alsoKnownAs->items) ? $artist->alsoKnownAs->items : array(),
                'image'          => $this->image($photo),
                'albums'         => $albums,
                'members'        => $this->artists($artist->members ?? null),
                'memberOf'       => $this->artists($artist->projects ?? null),
            )),
            Jkl_StructuredData::breadcrumbs(array(
                array('Hhbd.pl', $this->absolute('/')),
                array('Wykonawcy', $this->absolute('/wykonawcy.html')),
                array($artist->qualifiedName, $url),
            )),
        ));
    }

    public function song($song)
    {
        $artists = $this->artists($song->artist ?? null);
        // What each did, as the page says it: a guest's role where it has one
        $contributors = array_merge($this->artists($song->featuring ?? null, 'gościnnie'), $this->artists($song->scratch ?? null, 'skrecze'));
        $album = !empty($song->featured->items) ? $song->featured->items[0] : null;
        $url = $this->absolute($song->getUrl());
        // Through the album and its artist: the song's own albumArtist can be a stand-in.
        $trail = array(array('Hhbd.pl', $this->absolute('/')));
        if (null !== $album && !empty($album->artist)) {
            $trail[] = array($album->artist->qualifiedName, $this->absolute($album->artist->getUrl()));
        }
        if (null !== $album) {
            $trail[] = array($album->title, $this->absolute($album->getUrl()));
        }
        $trail[] = array($song->title, $url);
        return Jkl_StructuredData::script(array(
            Jkl_StructuredData::song(array(
                'name'         => $song->title,
                'url'          => $url,
                'length'       => $song->duration,
                'artists'      => $artists,
                'album'        => null !== $album ? array('name' => $album->title, 'url' => $this->absolute($album->getUrl())) : null,
                'producers'    => $this->artists($song->music ?? null),
                'contributors' => $contributors,
            )),
            Jkl_StructuredData::breadcrumbs($trail),
        ));
    }

    public function label($label)
    {
        $albums = array();
        foreach (!empty($label->releases->items) ? $label->releases->items : array() as $album) {
            $albums[] = array(
                'name'      => $album->title,
                'url'       => $this->absolute($album->getUrl()),
                'artists'   => $this->mainArtists($album),
                'date'      => $album->releaseDate,
                'precision' => $album->releaseDatePrecision,
            );
        }
        $url = $this->absolute($label->getUrl());
        $organization = Jkl_StructuredData::label(array(
            'name'    => $label->name,
            'url'     => $url,
            'logo'    => $this->image($label->logo),
            // Only an address: some labels keep a bare host name or other text there
            'website' => 1 === preg_match('#^https?://[^\s/]+\.[^\s]+$#i', (string) $label->website) ? $label->website : null,
        ));
        $graph = array($organization);
        if (!empty($albums)) {
            $graph[] = Jkl_StructuredData::albumList(array('name' => 'Lista wydawnictw ' . $label->name, 'label' => $organization['@id'], 'albums' => $albums));
        }
        $graph[] = Jkl_StructuredData::breadcrumbs(array(
            array('Hhbd.pl', $this->absolute('/')),
            array('Wytwórnie', $this->absolute('/wytwornie.html')),
            array($label->name, $url),
        ));
        return Jkl_StructuredData::script($graph);
    }

    /** What hhbd.pl is, and how to search it, for its home page (#218) */
    public function site()
    {
        $home = $this->absolute('/');
        return Jkl_StructuredData::script(array(
            Jkl_StructuredData::website(array(
                'name'        => 'Hhbd.pl',
                'url'         => $home,
                'description' => self::DESCRIPTION,
                'search'      => $this->absolute('/szukaj.html') . '?q={search_term_string}',
            )),
            $this->organizationData($home),
        ));
    }

    /** Who runs hhbd.pl, for the about page (#218) */
    public function organization()
    {
        return Jkl_StructuredData::script(array($this->organizationData($this->absolute('/'))));
    }

    private function organizationData($home)
    {
        return Jkl_StructuredData::organization(array(
            'name'          => 'Hhbd.pl',
            'alternateName' => 'Hip-Hopowa Baza Danych',
            'url'           => $home,
            // The launch the about page tells of
            'foundingDate'  => '2005-10',
            'description'   => self::DESCRIPTION,
        ));
    }

    /** An album's main artists, name and address, as its heading credits them */
    private function mainArtists($album)
    {
        $artists = array();
        foreach ($album->credits as $credit) {
            if ('main' === $credit['role'] && !empty($credit['artist'])) {
                $artists[] = array($credit['name'], $this->absolute($credit['artist']->getUrl()));
            }
        }
        return $artists;
    }

    /**
     * A list's artists, those with a page of their own, by the name the page links them by, its
     * qualifier only beside a namesake, and their address; with $role, each with its role, the
     * guest's own where it has one (#288)
     */
    private function artists($list, $role = null)
    {
        $artists = array();
        foreach (!empty($list->items) ? $list->items : array() as $artist) {
            if (empty($artist->id)) {
                continue;
            }
            $name = isset($artist->displayName) ? $artist->displayName : $artist->name;
            $artists[] = null === $role
                ? array($name, $this->absolute($artist->getUrl()))
                : array($name, $this->absolute($artist->getUrl()), !empty($artist->featType) ? $artist->featType : $role);
        }
        return $artists;
    }

    /** A path on this site as an absolute address, https:// behind the edge (#147) */
    private function absolute($path)
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        return $request->getScheme() . '://' . $request->getHttpHost() . $path;
    }

    /** A cover's or a photo's address, or null for none or a stand-in drawing, as Open Graph has it */
    private function image($path)
    {
        if (!Jkl_Og::isPicture($path)) {
            return null;
        }
        return 0 === strpos($path, 'http') ? $path : $this->absolute($path);
    }
}
