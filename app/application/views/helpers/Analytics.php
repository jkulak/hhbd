<?php

/**
 * Google's tags, in the code and in this order (#161, #162):
 *
 * - Consent Mode v2, everything denied until the visitor chooses in AdSense's certified consent
 *   message, which updates it; until then Google gets cookieless pings only.
 * - Google Analytics 4 through the Google tag (gtag.js), with what the page is: its content
 *   group, the album, artist, song, label or news item it shows, the search and its results, a
 *   logged-in user, an admin's visit as internal traffic. The measurement id comes from the
 *   environment (GA_MEASUREMENT_ID); without one, as locally and in CI, no analytics loads.
 * - AdSense's script when ads are on (SHOW_ADS), which also brings the consent message.
 *
 * A controller says what its page is: $this->view->Analytics()->page('album', $id, $name), and
 * adds what it knows: ->set('search_term', $query). A login or a registration ends in a
 * redirect, so it leaves its event in a short cookie (remember()) for the next page to send.
 * The layout prints head() in <head>.
 */
class Zend_View_Helper_Analytics extends Zend_View_Helper_Abstract
{
    /** The cookie a login or a registration leaves its event in, for the page it redirects to */
    public const EVENT_COOKIE = 'hhbd_ga_event';

    /** The events that cookie may carry, as GA4 names them */
    public const SERVER_EVENTS = array('login', 'sign_up');

    /** hhbd's AdSense publisher, the one GTM loaded before #161 */
    public const ADSENSE_CLIENT = 'ca-pub-6149271850793027';

    private $group;
    private $params = array();
    private $events = array();

    public function Analytics()
    {
        return $this;
    }

    /** What the page is, and on an entity's page which one */
    public function page($group, $id = null, $name = null)
    {
        $this->group = (string) $group;
        if (null !== $id) {
            $this->params['entity_id'] = (int) $id;
        }
        if (null !== $name && '' !== (string) $name) {
            $this->params['entity_name'] = (string) $name;
        }
        return $this;
    }

    /** A parameter of this page view: search_term, search_results */
    public function set($param, $value)
    {
        $this->params[(string) $param] = $value;
        return $this;
    }

    /** An event this page sends besides its page view */
    public function event($name, array $params = array())
    {
        $this->events[] = array((string) $name, $params);
        return $this;
    }

    /**
     * For a login or a registration, before the redirect: the event goes with the next page.
     * HttpOnly, as the server reads it, and gone after a minute if no page does.
     */
    public function remember($event)
    {
        if (!in_array($event, self::SERVER_EVENTS, true) || headers_sent()) {
            return;
        }
        setcookie(self::EVENT_COOKIE, $event, array(
            'expires' => time() + 60, 'path' => '/', 'secure' => self::secure(), 'httponly' => true, 'samesite' => 'Lax',
        ));
    }

    /** Everything Google's tags need, for <head>; nothing where no tag is on */
    public function head()
    {
        $config = Zend_Registry::get('Config_App');
        $measurementId = self::measurementIdOf(isset($config['gaMeasurementId']) ? $config['gaMeasurementId'] : '');
        $ads = !empty($config['showAds']);
        if (null === $measurementId && !$ads) {
            return '';
        }
        $events = $this->events;
        if (isset($_COOKIE[self::EVENT_COOKIE]) && in_array($_COOKIE[self::EVENT_COOKIE], self::SERVER_EVENTS, true)) {
            $events[] = array($_COOKIE[self::EVENT_COOKIE], array('method' => 'email'));
            if (!headers_sent()) {
                setcookie(self::EVENT_COOKIE, '', array('expires' => time() - 3600, 'path' => '/', 'secure' => self::secure(), 'httponly' => true, 'samesite' => 'Lax'));
            }
        }
        return self::script($measurementId, $this->config(), $events, $ads ? self::ADSENSE_CLIENT : null);
    }

    /** The page view's parameters: its group, what the controller set, and who is visiting */
    private function config()
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $group = null !== $this->group ? $this->group
            : self::groupOf($request ? $request->getControllerName() : '', $request ? $request->getActionName() : '');
        $loggedIn = Zend_Auth::getInstance()->hasIdentity();
        $config = array('content_group' => $group) + $this->params;
        $config['user_properties'] = array('logged_in' => $loggedIn ? 'yes' : 'no');
        if ($this->view->IsAdmin()) {
            $config['traffic_type'] = 'internal';
        }
        return $config;
    }

    /**
     * The scripts, in order: the consent defaults, GA4's tag with the page's parameters and
     * events when there is a measurement id, AdSense's script when there is a publisher.
     */
    public static function script($measurementId, array $config, array $events, $adsenseClient)
    {
        $json = function ($value) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        };
        $lines = array(
            '<script>',
            'window.dataLayer = window.dataLayer || [];',
            'function gtag(){dataLayer.push(arguments);}',
            "gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', wait_for_update: 500});",
            "gtag('set', 'ads_data_redaction', true);",
        );
        if (null !== $measurementId) {
            $lines[] = "gtag('js', new Date());";
            $lines[] = "gtag('config', " . $json($measurementId) . ', ' . $json((object) $config) . ');';
            foreach ($events as $event) {
                $lines[] = "gtag('event', " . $json($event[0]) . ', ' . $json((object) $event[1]) . ');';
            }
        }
        $lines[] = '</script>';
        if (null !== $measurementId) {
            $lines[] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($measurementId) . '"></script>';
        }
        if (null !== $adsenseClient) {
            $lines[] = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode($adsenseClient) . '" crossorigin="anonymous"></script>';
        }
        return implode("\n", $lines) . "\n";
    }

    /** A GA4 measurement id as the environment gave it, or null for none or a malformed one */
    public static function measurementIdOf($value)
    {
        $value = trim((string) $value);
        return 1 === preg_match('/^G-[A-Z0-9]{4,20}$/', $value) ? $value : null;
    }

    /** What a page is, by its controller and action, where the controller does not say */
    public static function groupOf($controller, $action)
    {
        switch ($controller) {
            case 'album':
            case 'artist':
            case 'label':
            case 'song':
            case 'news':
                return 'view' === $action ? $controller : $controller . ' list';
            case 'index':
                return 'index' === $action ? 'home' : $action;
            case 'top':
                return 'top10';
            case 'user':
                return 'view' === $action ? 'user' : 'account';
            case 'search':
                return 'search';
            case 'error':
                return 'error';
            default:
                return '' === (string) $controller ? 'other' : $controller;
        }
    }

    private static function secure()
    {
        return isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'];
    }
}
