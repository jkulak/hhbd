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
  
  function __construct()
  {
    # code...
  }

  public static function word_split($str, $words=15)
  {
    $arr = preg_split("/[\s]+/", $str, $words + 1);
    $arr = array_slice($arr, 0, $words);
    return join(' ', $arr);
  }

  public static function trim_str($string, $len, $addDots = true)
  {
    if (strlen($string) > $len) {
      $result = substr($string, 0, strpos($string, ' ', $len) - 1) . (($addDots)?'...':'');
    }
    else 
    {
      $result = $string . (($addDots)?'...':'');
    }
    
    return $result;
  }
}
