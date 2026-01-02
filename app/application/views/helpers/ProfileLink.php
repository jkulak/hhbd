<?php
/**
 * ProfileLink helper
 *
 * Call as $this->profileLink() in your layout script
 */
class Zend_View_Helper_ProfileLink extends Zend_View_Helper_Abstract 
{
  public function profileLink()
  {
    $auth = Zend_Auth::getInstance();
    $user = $auth->getIdentity();
    return '<a href="' . $this->view->url(array($user->usr_display_name, $user->usr_id), 'user') . '">' . $user->usr_display_name .  '</a>';
  }
}