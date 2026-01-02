<?php
/**
 * LoggedIn helper
 *
 * Call as $this->IsAdmin() to check if logged in user is admin,
 * returns false, when no one is logged in
 */
class Zend_View_Helper_IsAdmin extends Zend_View_Helper_Abstract 
{
  public function IsAdmin()
  {
    $auth = Zend_Auth::getInstance();
    return (($auth->hasIdentity()) and ($auth->getIdentity()->usr_is_admin == 'yes')); 
  }
}