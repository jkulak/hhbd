<?php
class Zend_View_Helper_RestUrl extends Zend_View_Helper_Abstract
{
    function restUrl($params) {
        $result = array();
        foreach($params as $key => $value) {
            $result[] = $key .'='. $value;
        }

        $restParams = implode('&', $result);
        $router = Zend_Controller_Front::getInstance()->getRouter();
        // return $router->assemble($urlOptions, $name, $reset, $encode);
        $url = new Zend_View_Helper_Url();

        return $url->url($params, $router->getCurrentRouteName()) . '?' .  $restParams . '#content';
        // return '#content';
    }
}
