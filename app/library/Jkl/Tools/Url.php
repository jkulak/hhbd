<?php

/**
 * Url
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package Tools
 **/

class Jkl_Tools_Url
{
    public function __construct()
    {
        # code...
    }

    public static function createUrl($string)
    {
        // $string = urlencode($string);
        return str_replace(array('/', '?', '&', '#'), ' ', $string);
    }

    /**
     * Create ASCII-safe filename from string
     * Transliterates Polish characters and removes special chars
     *
     * @param string $string Input string (e.g., "Hemp Gru - Jedność")
     * @return string Safe filename (e.g., "hemp-gru-jednosc")
     */
    public static function createSafeFilename($string)
    {
        // Polish character transliteration
        $polish = array(
          'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
          'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
          'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
          'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z'
        );

        // Convert to lowercase
        $string = mb_strtolower($string, 'UTF-8');

        // Transliterate Polish characters
        $string = strtr($string, $polish);

        // Replace spaces and special characters with hyphens
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);

        // Remove leading/trailing hyphens and collapse multiple hyphens
        $string = trim($string, '-');
        $string = preg_replace('/-+/', '-', $string);

        return $string;
    }
}
