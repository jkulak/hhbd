<?php

/**
 * schema.org JSON-LD for an album's, an artist's or a song's page (#195): what the page is, and
 * the path to it, from the containers the page shows, at absolute addresses. Jkl_StructuredData
 * builds the JSON; a view prints $this->StructuredData()->album($this->album).
 */
class Zend_View_Helper_StructuredData extends Zend_View_Helper_Abstract
{
    public function StructuredData()
    {
        return $this;
    }

    public function album($album)
    {
        $artists = array();
        foreach ($album->credits as $credit) {
            if ('main' === $credit['role'] && !empty($credit['artist'])) {
                $artists[] = array($credit['name'], $this->absolute($credit['artist']->getUrl()));
            }
        }
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
        $artists = array();
        foreach (!empty($song->artist->items) ? $song->artist->items : array() as $artist) {
            if (!empty($artist->id)) {
                $artists[] = array($artist->qualifiedName, $this->absolute($artist->getUrl()));
            }
        }
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
                'name'    => $song->title,
                'url'     => $url,
                'length'  => $song->duration,
                'artists' => $artists,
                'album'   => null !== $album ? array('name' => $album->title, 'url' => $this->absolute($album->getUrl())) : null,
            )),
            Jkl_StructuredData::breadcrumbs($trail),
        ));
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
