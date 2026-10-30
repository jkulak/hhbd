<?php

/**
 * One Polish sentence of facts for the top of an album's, an artist's, a song's or a label's
 * page (#219, #287), the kind search engines and the assistants answering from them quote, and
 * models trained on the page keep, as they mostly drop the JSON-LD's script: who, what, when,
 * by which label, how many tracks, who made a song. Built from plain arrays, as Jkl_StructuredData is, so each case is testable;
 * Zend_View_Helper_Summary hands it the containers' values. Plain text: the view escapes it.
 *
 * Each part that is not known is left out, and the sentence still reads: the names come first,
 * "Mes – „Ała.”", so no name needs a Polish case it cannot be put in.
 */
class Jkl_Summary
{
    /** A release type's noun, and its gender for the participle after it */
    private const RELEASE_NOUNS = array(
        'album'       => array('album', 'm'),
        'ep'          => array('EP-ka', 'f'),
        'mixtape'     => array('mixtape', 'm'),
        'compilation' => array('kompilacja', 'f'),
        'beat_tape'   => array('beat tape', 'm'),
        'single'      => array('singiel', 'm'),
        'other'       => array('wydawnictwo', 'n'),
    );

    private const RELEASED = array('m' => 'wydany', 'f' => 'wydana', 'n' => 'wydane');
    private const ANNOUNCED = array('m' => 'zapowiedziany', 'f' => 'zapowiedziana', 'n' => 'zapowiedziane');

    /** The months as "w maju 2016" has them */
    private const IN_MONTH = array(1 => 'styczniu', 'lutym', 'marcu', 'kwietniu', 'maju', 'czerwcu', 'lipcu', 'sierpniu', 'wrześniu', 'październiku', 'listopadzie', 'grudniu');

    /** The months as "na maj 2026" has them */
    private const FOR_MONTH = array(1 => 'styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień');

    /**
     * "Białas & Lanek – „In Hajs We Trust”, album wydany 5 września 2017 przez SBM Label: 12
     * utworów, 28:28."
     *
     * @param array $album title, artists (the names as the heading has them), type (a release
     *                     type, Model_Album_Container's), announced, date (Y-m-d or null),
     *                     precision (day, month, year), label (a name or null), selfReleased,
     *                     tracks (a count), duration (mm:ss or anything else for none)
     */
    public static function album(array $album)
    {
        list($noun, $gender) = isset(self::RELEASE_NOUNS[$album['type']]) ? self::RELEASE_NOUNS[$album['type']] : self::RELEASE_NOUNS['other'];
        $when = self::when($album['date'], $album['precision'], !empty($album['announced']));
        $by = !empty($album['label']) ? 'przez ' . $album['label'] : (!empty($album['selfReleased']) ? 'własnym nakładem' : '');

        $sentence = trim($album['artists']) . ' – „' . trim($album['title']) . '”, ' . $noun;
        if (!empty($album['announced']) || '' !== $when || '' !== $by) {
            $participle = !empty($album['announced']) ? self::ANNOUNCED[$gender] : self::RELEASED[$gender];
            $sentence .= ' ' . implode(' ', array_filter(array($participle, $when, $by), 'strlen'));
        }
        $tracks = (int) $album['tracks'];
        if ($tracks > 0) {
            $sentence .= ': ' . $tracks . ' ' . Jkl_Tools_String::plural($tracks, 'utwór', 'utwory', 'utworów');
            if (1 === preg_match('/^\d+:\d{2}$/', (string) $album['duration']) && '00:00' !== $album['duration']) {
                $sentence .= ', ' . $album['duration'];
            }
        }
        return $sentence . '.';
    }

    /**
     * "Mes – wykonawca, znany też jako Dj M-easy, Mestentyp i Ten Typ Mes; na Hhbd.pl 6 albumów
     * z lat 2003–2021."
     *
     * @param array $artist name, band (a group rather than one person), otherNames, albums (a
     *                      count), from and to (the first and last year, null for none)
     */
    public static function artist(array $artist)
    {
        $sentence = trim($artist['name']) . ' – ' . (!empty($artist['band']) ? 'skład' : 'wykonawca');
        $others = array_values(array_filter(array_map('trim', (array) $artist['otherNames']), 'strlen'));
        if (!empty($others)) {
            $sentence .= ', znany też jako ' . self::listed($others);
        }
        $albums = (int) $artist['albums'];
        if ($albums > 0) {
            $sentence .= '; na Hhbd.pl ' . $albums . ' ' . Jkl_Tools_String::plural($albums, 'album', 'albumy', 'albumów');
            if (null !== $artist['from']) {
                $sentence .= $artist['from'] === $artist['to'] ? ' z ' . $artist['from'] . ' roku' : ' z lat ' . $artist['from'] . '–' . $artist['to'];
            }
        }
        return $sentence . '.';
    }

    /** How many albums a song's sentence names before it counts the rest, and a label's artists */
    private const NAMED = 3;
    private const NAMED_ARTISTS = 5;

    /**
     * "Wdowa – „Pogoda”, utwór z albumu „Superextra” (Alkopoligamia, 2010); muzyka: Beatmo;
     * gościnnie: Pezet (wokal); skrecze: Dj Technik."
     *
     * @param array $song title, artists (the names of who performs it), albums (each a title, a
     *                    year, '' or 0 for none, and a label, a name or null), music, featuring
     *                    and scratch (names, a guest's with what it did as the page has it)
     */
    public static function song(array $song)
    {
        $artists = self::names($song['artists']);
        $sentence = (empty($artists) ? '' : self::listed($artists) . ' – ') . '„' . trim($song['title']) . '”, utwór';
        $albums = array();
        foreach ($song['albums'] as $album) {
            $facts = array_values(array_filter(array(trim((string) $album['label']), (int) $album['year'] > 0 ? (string) (int) $album['year'] : ''), 'strlen'));
            $albums[] = '„' . trim($album['title']) . '”' . (empty($facts) ? '' : ' (' . implode(', ', $facts) . ')');
        }
        if (!empty($albums)) {
            // Four named read as well as three and one more counted
            $rest = count($albums) > self::NAMED + 1 ? count($albums) - self::NAMED : 0;
            $named = $rest > 0 ? array_slice($albums, 0, self::NAMED) : $albums;
            $sentence .= ' z ' . (1 === count($albums) ? 'albumu ' : 'albumów ')
                . ($rest > 0 ? implode(', ', $named) . ' i ' . $rest . ' innych' : self::listed($named));
        }
        foreach (array('music' => 'muzyka', 'featuring' => 'gościnnie', 'scratch' => 'skrecze') as $key => $word) {
            $names = self::names($song[$key]);
            if (!empty($names)) {
                $sentence .= '; ' . $word . ': ' . self::listed($names);
            }
        }
        return $sentence . '.';
    }

    /**
     * "Alkopoligamia – wytwórnia; na Hhbd.pl 12 albumów z lat 2009–2018, wykonawcy: Wdowa, Ten
     * Typ Mes, Pezet, Małpa, Rasmentalism i inni."
     *
     * @param array $label name, albums (a count), from and to (the first and last year, null for
     *                     none), artists (the names, the one with most albums first)
     */
    public static function label(array $label)
    {
        $sentence = trim($label['name']) . ' – wytwórnia';
        $albums = (int) $label['albums'];
        if ($albums > 0) {
            $sentence .= '; na Hhbd.pl ' . $albums . ' ' . Jkl_Tools_String::plural($albums, 'album', 'albumy', 'albumów');
            if (null !== $label['from']) {
                $sentence .= $label['from'] === $label['to'] ? ' z ' . $label['from'] . ' roku' : ' z lat ' . $label['from'] . '–' . $label['to'];
            }
            $artists = self::names($label['artists']);
            if (!empty($artists)) {
                $sentence .= ', ' . (1 === count($artists) ? 'wykonawca: ' : 'wykonawcy: ')
                    . (count($artists) > self::NAMED_ARTISTS ? implode(', ', array_slice($artists, 0, self::NAMED_ARTISTS)) . ' i inni' : self::listed($artists));
            }
        }
        return $sentence . '.';
    }

    /**
     * When, to the precision it is known: "5 września 2017", "w maju 2016", "w 2016 roku"; for
     * an announcement "na 5 września 2026", "na maj 2026", "na 2026 rok"; '' for no date
     */
    public static function when($date, $precision, $announced)
    {
        if (empty($date) || 1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $date, $m)) {
            return '';
        }
        list(, $year, $month, $day) = $m;
        $month = (int) $month;
        if ('year' === $precision || 0 === $month) {
            return $announced ? 'na ' . $year . ' rok' : 'w ' . $year . ' roku';
        }
        if ('month' === $precision || 0 === (int) $day) {
            return $announced ? 'na ' . self::FOR_MONTH[$month] . ' ' . $year : 'w ' . self::IN_MONTH[$month] . ' ' . $year;
        }
        return ($announced ? 'na ' : '') . (int) $day . ' ' . Jkl_Tools_Date::$months[$month] . ' ' . $year;
    }

    /** The names that are there, trimmed, once each, in their order */
    private static function names($names)
    {
        return array_values(array_unique(array_filter(array_map('trim', (array) $names), 'strlen')));
    }

    /** "A", "A i B", "A, B i C" */
    private static function listed(array $names)
    {
        $last = array_pop($names);
        return empty($names) ? $last : implode(', ', $names) . ' i ' . $last;
    }
}
