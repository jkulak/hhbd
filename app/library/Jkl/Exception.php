<?php

/**
* 
*/
class Jkl_Exception extends Zend_Exception
{
  function __construct($message = '', $code = null)
  {
    parent::__construct($message, $code);
  }
  
  public function __toString()
  {
    $str = $this->message;
    $str .= ' exception';
    return $str;
  }
}

?>