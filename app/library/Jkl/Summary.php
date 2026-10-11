<?php

/**
 * One Polish sentence of facts for the top of an album's or an artist's page (#219), the kind
 * search engines and the assistants answering from them quote: who, what, when, by which label,
 * how many tracks. Built from plain arrays, as Jkl_StructuredData is, so each case is testable;
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

    /** "A", "A i B", "A, B i C" */
    private static function listed(array $names)
    {
        $last = array_pop($names);
        return empty($names) ? $last : implode(', ', $names) . ' i ' . $last;
    }
}
