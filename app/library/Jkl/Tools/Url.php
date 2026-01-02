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
  
  function __construct()
  {
    # code...
  }
  
  public static function createUrl($string)
  {
    // $string = urlencode($string);
    return str_replace(array('/', '?', '&', '#'), ' ', $string);
  }
}
