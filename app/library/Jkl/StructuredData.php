<?php

/**
 * What a page is, in schema.org's terms, for search engines (#195): an album, an artist or a
 * song, and the path to it. Built from plain arrays, so it is the same for any page that hands
 * it one; Zend_View_Helper_StructuredData hands it the containers' values.
 */
class Jkl_StructuredData
{
    public const CONTEXT = 'https://schema.org';

    /** How a release type maps onto schema.org's: the kind of release, and how it was made */
    private const RELEASE_TYPES = array(
        'album'       => array('AlbumRelease', 'StudioAlbum'),
        'ep'          => array('EPRelease', 'StudioAlbum'),
        'single'      => array('SingleRelease', 'StudioAlbum'),
        'mixtape'     => array('AlbumRelease', 'MixtapeAlbum'),
        'compilation' => array('AlbumRelease', 'CompilationAlbum'),
        'beat_tape'   => array('AlbumRelease', 'StudioAlbum'),
        'other'       => array('AlbumRelease', null),
    );

    /** A date at the precision hhbd has it: 2016, 2016-11 or 2016-11-03; null for none */
    public static function date($date, $precision)
    {
        if (empty($date) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $date)) {
            return null;
        }
        switch ($precision) {
            case 'year':
                return substr($date, 0, 4);
            case 'month':
                return substr($date, 0, 7);
            default:
                return substr($date, 0, 10);
        }
    }

    /** A track's length as hhbd shows it, 03:12, in ISO 8601: PT3M12S; null for none */
    public static function duration($length)
    {
        if (1 !== preg_match('/^(\d+):(\d{2})$/', (string) $length, $m)) {
            return null;
        }
        return 'PT' . (int) $m[1] . 'M' . (int) $m[2] . 'S';
    }

    /**
     * @param array $trail array(name, url) from the site down to the page itself
     */
    public static function breadcrumbs(array $trail)
    {
        $items = array();
        foreach (array_values($trail) as $i => list($name, $url)) {
            $items[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url);
        }
        return array('@type' => 'BreadcrumbList', 'itemListElement' => $items);
    }

    /**
     * @param array $album name, url, artists (array of name, url), date, precision, type, label
     *                     (name, url, or null), selfReleased, image (or null), tracks (array of
     *                     name, url, length)
     */
    public static function album(array $album)
    {
        $types = isset(self::RELEASE_TYPES[$album['type']]) ? self::RELEASE_TYPES[$album['type']] : self::RELEASE_TYPES['other'];
        $data = array(
            '@type'            => 'MusicAlbum',
            'name'             => $album['name'],
            'url'              => $album['url'],
            'byArtist'         => array_map(array(self::class, 'group'), $album['artists']),
            'genre'            => 'Hip-hop',
            'albumReleaseType' => 'https://schema.org/' . $types[0],
        );
        if (null !== $types[1]) {
            $data['albumProductionType'] = 'https://schema.org/' . $types[1];
        }
        $date = self::date($album['date'], $album['precision']);
        if (null !== $date) {
            $data['datePublished'] = $date;
        }
        if (!empty($album['label'])) {
            $data['recordLabel'] = array('@type' => 'Organization', 'name' => $album['label']['name'], 'url' => $album['label']['url']);
        }
        if (!empty($album['image'])) {
            $data['image'] = $album['image'];
        }
        if (!empty($album['tracks'])) {
            $data['numTracks'] = count($album['tracks']);
            $items = array();
            foreach (array_values($album['tracks']) as $i => $track) {
                $items[] = array('@type' => 'ListItem', 'position' => $i + 1, 'item' => self::recording($track));
            }
            $data['track'] = array('@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $items);
        }
        return $data;
    }

    /**
     * @param array $artist name, url, alternateNames, image (or null), albums (array of name, url)
     */
    public static function artist(array $artist)
    {
        $data = self::group(array($artist['name'], $artist['url'])) + array('genre' => 'Hip-hop');
        if (!empty($artist['alternateNames'])) {
            $data['alternateName'] = array_values($artist['alternateNames']);
        }
        if (!empty($artist['image'])) {
            $data['image'] = $artist['image'];
        }
        if (!empty($artist['albums'])) {
            $data['album'] = array_map(function ($album) {
                return array('@type' => 'MusicAlbum', 'name' => $album[0], 'url' => $album[1]);
            }, array_values($artist['albums']));
        }
        return $data;
    }

    /**
     * @param array $song name, url, length, artists (array of name, url), album (name, url, or null)
     */
    public static function song(array $song)
    {
        $data = self::recording($song);
        if (!empty($song['artists'])) {
            $data['byArtist'] = array_map(array(self::class, 'group'), $song['artists']);
        }
        if (!empty($song['album'])) {
            $data['inAlbum'] = array('@type' => 'MusicAlbum', 'name' => $song['album']['name'], 'url' => $song['album']['url']);
        }
        return $data;
    }

    /**
     * The script element, its JSON escaped so no text on the page, a title with "</script>" in
     * it, can end it early
     */
    public static function script(array $graph)
    {
        $json = json_encode(
            array('@context' => self::CONTEXT, '@graph' => array_values($graph)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /** An artist, credited on a release or a song: name and address */
    private static function group(array $artist)
    {
        return array('@type' => 'MusicGroup', 'name' => $artist[0], 'url' => $artist[1]);
    }

    private static function recording(array $track)
    {
        $data = array('@type' => 'MusicRecording', 'name' => $track['name'], 'url' => $track['url']);
        $duration = isset($track['length']) ? self::duration($track['length']) : null;
        if (null !== $duration) {
            $data['duration'] = $duration;
        }
        return $data;
    }
}
