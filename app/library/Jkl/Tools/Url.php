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

    /**
     * Create clean URL/filename from string
     * Transliterates Polish characters, converts to lowercase, removes special chars
     *
     * @param string $string Input string (e.g., "Hemp Gru - Jedność")
     * @return string Clean URL (e.g., "hemp-gru-jednosc")
     */
    public static function createUrl($string)
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

        // Replace spaces and special characters with dashes
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);

        // Remove leading/trailing dashes and collapse multiple dashes
        $string = trim($string, '-');
        $string = preg_replace('/-+/', '-', $string);

        return $string;
    }

    // TODO: Check if it's used anywhere and consider removing
    /**
     * Alias for createUrl() for backwards compatibility
     * @deprecated Use createUrl() instead
     */
    public static function createSafeFilename($string)
    {
        return self::createUrl($string);
    }
}
