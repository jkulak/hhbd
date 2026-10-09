<?php

/**
 * Whether the visitor is a logged-in admin, who sees what an import left to settle (#103).
 * Call as $this->IsAdmin() in a view, or $this->view->IsAdmin() in a controller.
 */
class Zend_View_Helper_IsAdmin extends Zend_View_Helper_Abstract
{
    public function IsAdmin()
    {
        $auth = Zend_Auth::getInstance();
        return self::identityIsAdmin($auth->hasIdentity() ? $auth->getIdentity() : null);
    }

    /** @param object|null $identity what the login stored: usr_is_admin is 'yes' for an admin */
    public static function identityIsAdmin($identity)
    {
        return is_object($identity) && isset($identity->usr_is_admin) && 'yes' === $identity->usr_is_admin;
    }
}
