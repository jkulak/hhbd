<?php
/**
 * LoggedIn helper
 *
 * Call as $this->LoggedIn() to check if user is logged in
 */
class Zend_View_Helper_LoggedIn extends Zend_View_Helper_Abstract 
{
  public function LoggedIn()
  {
    $auth = Zend_Auth::getInstance();
    if ($auth->hasIdentity()) {
      return $auth->getIdentity();
    }
    return false;
  }
}