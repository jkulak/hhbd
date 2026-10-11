<?php

/**
 * The catalogue in figures, for the about page (#218): the released albums a visitor can open
 * and the years they came out in, the artists, the songs on those albums and the labels,
 * counted at each request, so the sentence is never out of date. Call as $this->Figures(); it
 * returns one Polish sentence, plain text.
 */
class Zend_View_Helper_Figures extends Zend_View_Helper_Abstract
{
    public function Figures()
    {
        list($albums, $from, $to) = Model_Album_Api::getInstance()->getShownFigures();
        return self::describe(array(
            'albums'  => $albums,
            'from'    => $from,
            'to'      => $to,
            'artists' => Model_Artist_Api::getInstance()->getCount(),
            'songs'   => Model_Song_Api::getInstance()->getShownCount(),
            'labels'  => Model_Label_Api::getInstance()->getCount(),
        ));
    }

    /**
     * @param array $figures albums, from and to (the first and last year, null for none),
     *                       artists, songs, labels
     */
    public static function describe(array $figures)
    {
        $albums = self::count($figures['albums'], 'album', 'albumy', 'albumów');
        if (null !== $figures['from']) {
            $albums .= ' ' . Jkl_Tools_String::plural($figures['albums'], 'wydany', 'wydane', 'wydanych')
                . ($figures['from'] === $figures['to'] ? ' w ' . $figures['from'] . ' roku' : ' w latach ' . $figures['from'] . '–' . $figures['to']);
        }
        return 'Hhbd.pl opisuje dziś ' . $albums . ', '
            . self::count($figures['artists'], 'wykonawcę', 'wykonawców', 'wykonawców') . ', '
            . self::count($figures['songs'], 'utwór', 'utwory', 'utworów') . ' i '
            . self::count($figures['labels'], 'wytwórnię', 'wytwórnie', 'wytwórni') . '.';
    }

    /** A count and its noun, the thousands apart as ViewsFor has them */
    private static function count($count, $one, $few, $many)
    {
        return number_format((int) $count, 0, ',', "\u{00A0}") . ' ' . Jkl_Tools_String::plural($count, $one, $few, $many);
    }
}
