<?php

/**
 * One token per session for the forms that change data (#103): a form carries it, and the
 * action that takes the form refuses a request without it, so another site cannot make a
 * logged-in admin's browser post to hhbd.
 */
class Jkl_Csrf
{
    private const NAMESPACE_NAME = 'csrf';

    public static function token()
    {
        $session = new Zend_Session_Namespace(self::NAMESPACE_NAME);
        if (empty($session->token)) {
            $session->token = bin2hex(random_bytes(32));
        }
        return $session->token;
    }

    public static function isValid($token)
    {
        $session = new Zend_Session_Namespace(self::NAMESPACE_NAME);
        return is_string($token) && !empty($session->token) && hash_equals($session->token, $token);
    }
}
