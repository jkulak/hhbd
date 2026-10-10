<?php

class Zend_View_Helper_RestUrl extends Zend_View_Helper_Abstract
{
    public function restUrl($params)
    {
        // Encoded, and escaped for the attribute it goes in: a search's query is whatever was
        // typed (#151)
        $restParams = htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8');
        $router = Zend_Controller_Front::getInstance()->getRouter();
        // return $router->assemble($urlOptions, $name, $reset, $encode);
        $url = new Zend_View_Helper_Url();

        return $url->url($params, $router->getCurrentRouteName()) . '?' .  $restParams . '#content';
        // return '#content';
    }
}
