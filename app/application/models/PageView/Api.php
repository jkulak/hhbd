<?php

/**
 * Page views, counted when a page is shown (#68): the total since the site began in each
 * table's viewed, and the day's in page_views, for popularity over a period (#194).
 *
 * Counted on the server, as the page renders: a script calling /stat did it once, and nothing
 * has called it since before this repository's first commit (January 2026), so the totals
 * stood still and /stat let anyone add to them. A view counts once per visit, page and day, so
 * a reload adds nothing; a bot, a HEAD request and an admin's view count for nothing. Only the
 * numbers are kept, nothing about who.
 */
class Model_PageView_Api extends Jkl_Model_Api
{
    /** The kinds of page counted, and their tables */
    public const TYPES = array('album' => 'albums', 'artist' => 'artists', 'song' => 'songs', 'label' => 'labels', 'news' => 'news');

    /** What a crawler, a link preview or a script calls itself, as against a browser */
    private const BOTS = '/bot|crawl|spider|slurp|mediapartners|google-|feedfetcher|facebookexternalhit|embedly|preview|archiver|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python|httpclient|java\/|go-http|libwww|okhttp|axios|node-fetch|scrapy/i';

    private const SESSION = 'pageViews';

    private static $_instance;

    /** @return Model_PageView_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /** Whether a request with this method and user agent, from an admin or not, is a view to count */
    public static function counts($method, $userAgent, $isAdmin)
    {
        if ('GET' !== strtoupper((string) $method) || $isAdmin) {
            return false;
        }
        $userAgent = trim((string) $userAgent);
        return '' !== $userAgent && 1 !== preg_match(self::BOTS, $userAgent);
    }

    /**
     * Counts a view of page $type $id, once per visit and day
     *
     * @return bool whether it counted
     */
    public function count($type, $id, $isAdmin)
    {
        $id = (int) $id;
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (!isset(self::TYPES[$type]) || $id < 1 || !self::counts($method, $agent, $isAdmin)) {
            return false;
        }
        $day = date('Y-m-d');
        $session = new Zend_Session_Namespace(self::SESSION);
        $seen = ($session->day === $day && is_array($session->seen)) ? $session->seen : array();
        $key = $type . ':' . $id;
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        $session->day = $day;
        $session->seen = $seen;

        $this->_db->query('UPDATE `' . self::TYPES[$type] . '` SET viewed = viewed + 1 WHERE id = ?', array($id));
        $this->_db->query(
            'INSERT INTO page_views (entity_type, entity_id, day, views) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE views = views + 1',
            array($type, $id, $day)
        );
        return true;
    }
}
