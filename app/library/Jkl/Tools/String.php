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
     * A meta description from a text that may be HTML (#147): no tags and no entities, one space
     * between words, and at most $length characters, cut after a word with "..." added. Counted
     * in characters, not bytes, so a Polish letter is never cut in two.
     */
    public static function metaDescription($text, $length = 160)
    {
        $text = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|h[1-6])>/i', ' ', (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length - 3, 'UTF-8');
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if (false !== $space && $space > $length / 2) {
            $cut = mb_substr($cut, 0, $space, 'UTF-8');
        }
        return rtrim($cut, ' ,.;:-') . '...';
    }

    public static function word_split($str, $words = 15)
    {
        $arr = preg_split("/[\s]+/", $str, $words + 1);
        $arr = array_slice($arr, 0, $words);
        return join(' ', $arr);
    }

    public static function trim_str($string, $len, $addDots = true)
    {
        if (strlen($string) > $len) {
            $result = substr($string, 0, strpos($string, ' ', $len) - 1) . (($addDots) ? '...' : '');
        } else {
            $result = $string . (($addDots) ? '...' : '');
        }

        return $result;
    }
}
