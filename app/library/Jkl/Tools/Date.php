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
  
  /*
  * A date as a page shows it, to the precision it is known: "15 stycznia 2020",
  * "któregoś maja 2020" (the month) or "2020" (the year). Without a precision it is read from
  * the date itself, as the albums held it before migration 0015: zero parts for what is not
  * known (2020-05-00, 2020-00-00). With one, the date is a whole one (2020-05-01) and the
  * precision says which parts mean anything (#54).
  */
  public static function getNormalDate($date, $precision = null)
  {
    if (null === $precision) {
      $precision = self::precisionOf($date);
    }
    $year = substr($date, 0, 4);
    if ('year' === $precision || (int)substr($date, 5, 2) == 0) {
      return $year;
    }
    $day = ('day' === $precision && (int)substr($date, 8, 2) <> 0) ? (int)substr($date, 8, 2) . ' ' : 'któregoś ';
    return $day . self::$months[(int)substr($date, 5, 2)] . ' ' . $year;
  }

  /*
  * How much of a date with zero parts is known: day, month or year
  */
  public static function precisionOf($date)
  {
    if ((int)substr($date, 5, 2) == 0) {
      return 'year';
    }
    return ((int)substr($date, 8, 2) == 0) ? 'month' : 'day';
  }
}
