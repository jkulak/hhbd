<?php

/**
 * Url
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package Tools
 **/

class Jkl_Tools_String
{
    public function __construct()
    {
        # code...
    }

    /**
     * A meta description from a text that may be HTML (#147): its excerpt (below), up to 160
     * characters.
     */
    public static function metaDescription($text, $length = 160)
    {
        return self::excerpt($text, $length);
    }

    /**
     * Plain text from a text that may be HTML, at most $length characters (#147, #163): no tags
     * and no entities, a space where a line or a paragraph ended, one space between words, and
     * cut after a whole word with "..." when it cut. Not escaped: a view escapes it.
     */
    public static function excerpt($text, $length)
    {
        $text = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|h[1-6])>/i', ' ', (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
        return self::cut($text, $length, true);
    }

    public static function word_split($str, $words = 15)
    {
        $arr = preg_split("/[\s]+/", $str, $words + 1);
        $arr = array_slice($arr, 0, $words);
        return join(' ', $arr);
    }

    /**
     * A plain text cut to at most $len characters, after the last whole word that fits, with
     * "..." when it cut and only then (#163). It counted bytes, cut at the first space after the
     * limit less one character ("odpowiad..."), split Polish letters, and added "..." to every
     * text.
     */
    public static function trim_str($string, $len, $addDots = true)
    {
        return self::cut(trim((string) $string), $len, $addDots);
    }

    /**
     * $text at most $length characters long, "..." included when $dots: cut before the word the
     * limit falls in, unless that would leave less than half, as with one long word, where the
     * cut is in the word. Never inside a letter.
     */
    private static function cut($text, $length, $dots)
    {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        $room = max(1, $length - ($dots ? 3 : 0));
        $cut = mb_substr($text, 0, $room, 'UTF-8');
        if (' ' !== mb_substr($text, $room, 1, 'UTF-8')) {
            $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
            if (false !== $space && $space > $room / 2) {
                $cut = mb_substr($cut, 0, $space, 'UTF-8');
            }
        }
        $cut = rtrim($cut, ' ,.;:!?-');
        return $dots ? $cut . '...' : $cut;
    }
}
