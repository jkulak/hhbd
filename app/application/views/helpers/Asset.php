<?php

/**
 * A file from public/ at an address that changes with its content (#149): "/css/s.css?v=" and
 * the first eight characters of its MD5. Cloudflare and browsers keep /css/ and /js/ for five
 * days, so a stylesheet changed under the same address would reach visitors days late, and
 * half of it with the old page. Call as $this->Asset('/css/s.css') in a view.
 */
class Zend_View_Helper_Asset extends Zend_View_Helper_Abstract
{
    /** @var array path => address, within one request */
    private static $addresses = array();

    public function Asset($path)
    {
        if (!isset(self::$addresses[$path])) {
            self::$addresses[$path] = self::address($path, APPLICATION_PATH . '/../public');
        }
        return self::$addresses[$path];
    }

    /** The address of $path, a file under $root: as it is for a file that is not there */
    public static function address($path, $root)
    {
        $file = rtrim($root, '/') . '/' . ltrim($path, '/');
        if (!is_file($file)) {
            return $path;
        }
        return $path . '?v=' . substr(md5_file($file), 0, 8);
    }
}
