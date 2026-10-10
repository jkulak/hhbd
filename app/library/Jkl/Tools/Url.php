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
     * What a slug is when nothing of the name is left: a name of symbols alone, or in a script
     * the tables below do not cover. A slug is never empty, or the page's address would be
     * "-p123.html", which no route matches (#155).
     */
    public const EMPTY_SLUG = 'x';

    /**
     * Latin letters with a diacritic, lowercase, as their base letters: Polish ones as before,
     * and every other from Latin-1 and Latin Extended-A and -B ("Doré" dore, "Wöyza" woyza,
     * "Áron Szilágyi" aron-szilagyi), made from Unicode's decompositions (#155).
     */
    private const LATIN = array(
        'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i',
        'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'ü' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ć' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd', 'ē' => 'e',
        'ĕ' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g',
        'ģ' => 'g', 'ĥ' => 'h', 'ħ' => 'h', 'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i',
        'ı' => 'i', 'ĳ' => 'ij', 'ĵ' => 'j', 'ķ' => 'k', 'ĸ' => 'k', 'ĺ' => 'l', 'ļ' => 'l',
        'ľ' => 'l', 'ŀ' => 'l', 'ł' => 'l', 'ń' => 'n', 'ņ' => 'n', 'ň' => 'n', 'ŉ' => 'n',
        'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o', 'œ' => 'oe', 'ŕ' => 'r', 'ŗ' => 'r', 'ř' => 'r',
        'ś' => 's', 'ŝ' => 's', 'ş' => 's', 'š' => 's', 'ţ' => 't', 'ť' => 't', 'ŧ' => 't',
        'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u', 'ŵ' => 'w',
        'ŷ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'ſ' => 's', 'ƒ' => 'f', 'ơ' => 'o',
        'ư' => 'u', 'ǆ' => 'dz', 'ǉ' => 'lj', 'ǌ' => 'nj', 'ǎ' => 'a', 'ǐ' => 'i', 'ǒ' => 'o',
        'ǔ' => 'u', 'ǖ' => 'u', 'ǘ' => 'u', 'ǚ' => 'u', 'ǜ' => 'u', 'ǟ' => 'a', 'ǡ' => 'a',
        'ǧ' => 'g', 'ǩ' => 'k', 'ǫ' => 'o', 'ǭ' => 'o', 'ǰ' => 'j', 'ǳ' => 'dz', 'ǵ' => 'g',
        'ǹ' => 'n', 'ǻ' => 'a', 'ȁ' => 'a', 'ȃ' => 'a', 'ȅ' => 'e', 'ȇ' => 'e', 'ȉ' => 'i',
        'ȋ' => 'i', 'ȍ' => 'o', 'ȏ' => 'o', 'ȑ' => 'r', 'ȓ' => 'r', 'ȕ' => 'u', 'ȗ' => 'u',
        'ș' => 's', 'ț' => 't', 'ȟ' => 'h', 'ȧ' => 'a', 'ȩ' => 'e', 'ȫ' => 'o', 'ȭ' => 'o',
        'ȯ' => 'o', 'ȱ' => 'o', 'ȳ' => 'y',
    );

    /**
     * Cyrillic, lowercase, transcribed the way a Polish reader says it (#155): "Игроки Улиц"
     * igroki-ulic, "Нам Хлам Клан" nam-chlam-klan. Russian, Ukrainian, Belarusian, and the
     * South Slavic letters.
     */
    private const CYRILLIC = array(
        'а' => 'a', 'б' => 'b', 'в' => 'w', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'jo',
        'ж' => 'z', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'ch', 'ц' => 'c', 'ч' => 'cz', 'ш' => 'sz', 'щ' => 'szcz', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'ju', 'я' => 'ja',
        'є' => 'je', 'і' => 'i', 'ї' => 'ji', 'ґ' => 'g', 'ў' => 'u',
        'ђ' => 'dj', 'ј' => 'j', 'љ' => 'lj', 'њ' => 'nj', 'ћ' => 'c', 'џ' => 'dz', 'ѓ' => 'g',
        'ќ' => 'k', 'ѕ' => 'dz',
    );

    /**
     * A slug from a name or a title, for an address: lowercase ASCII letters and digits, words
     * joined by dashes, every Latin letter with a diacritic as its base letter and Cyrillic
     * transcribed; never empty (EMPTY_SLUG). An address built of several parts slugs the name
     * alone and adds the id after it ("igroki-ulic" . "-p8228"), or an id would hide an empty
     * name.
     *
     * @param string $string Input string (e.g., "Hemp Gru - Jedność")
     * @return string Clean URL (e.g., "hemp-gru-jednosc")
     */
    public static function createUrl($string)
    {
        $string = mb_strtolower((string) $string, 'UTF-8');
        $string = strtr($string, self::LATIN + self::CYRILLIC);

        // Replace spaces and special characters with dashes
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);

        // Remove leading/trailing dashes and collapse multiple dashes
        $string = trim($string, '-');
        $string = preg_replace('/-+/', '-', $string);

        return '' === $string ? self::EMPTY_SLUG : $string;
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
