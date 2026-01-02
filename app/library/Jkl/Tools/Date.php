<?php
/**
 * Date
 *
 * @author Kuba
 * @version $Id$
 * @copyright __MyCompanyName__, 12 October, 2010
 * @package Tools
 **/
 
class Jkl_Tools_Date
{
  public static $months = array(1 => 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia');
  
  function __construct()
  {
    # code...
  }
  
  public static function getNormalDate($date)
  {    
    $year = substr($date, 0, 4);
    if ((int)substr($date, 5, 2) != 0) {
      $day = ((int)substr($date, 8, 2)<>0)?(int)substr($date, 8, 2) . ' ':'któregoś ';
      $month = self::$months[(int)substr($date, 5, 2)];
      return $day . $month . ' ' . $year;
    }
    return $year;
  }
}
