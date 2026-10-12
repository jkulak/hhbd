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

    /**
     * The periods popularity is counted over (#194), by the word their addresses end in, and
     * how a page says them; '' is all time, the totals in viewed.
     */
    public const PERIODS = array(
        'tydzien' => 'z ostatnich 7 dni',
        'miesiac' => 'z ostatnich 30 dni',
        'rok'     => 'w tym roku',
        ''        => 'wszech czasów',
    );

    private static $_instance;

    /** @return Model_PageView_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /** The address of a ranking: $base '/albumy/popularne', and the period's word after a dash */
    public static function rankingUrl($base, $period)
    {
        return $base . ('' === $period ? '' : '-' . $period) . '.html';
    }

    /**
     * The first day of a period that ends today, $today included, as Y-m-d: the last 7 days, the
     * last 30, this calendar year; null for all time.
     */
    public static function since($period, $today)
    {
        switch ($period) {
            case 'tydzien':
                return date('Y-m-d', strtotime($today . ' -6 days'));
            case 'miesiac':
                return date('Y-m-d', strtotime($today . ' -29 days'));
            case 'rok':
                return substr($today, 0, 4) . '-01-01';
            case '':
                return null;
        }
        throw new InvalidArgumentException(sprintf('No period "%s"', $period));
    }

    /**
     * The ids of type $type most viewed since $since, the most first, at most $count; $shown
     * narrows them, a condition on the table aliased t3 (an album's SHOWN)
     *
     * @return int[]
     */
    public function mostViewedSince($type, $since, $count, $shown = '1 = 1')
    {
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException(sprintf('No page type "%s"', $type));
        }
        $rows = $this->_db->fetchAll(
            'SELECT t3.id FROM page_views pv JOIN ' . self::TYPES[$type] . ' t3 ON t3.id = pv.entity_id
              WHERE pv.entity_type = ? AND pv.day >= ? AND ' . $shown . '
              GROUP BY t3.id ORDER BY SUM(pv.views) DESC, t3.id LIMIT ' . (int) $count,
            array($type, $since)
        );
        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * A page's views for an admin (#214): the last 7 days, the last 30, this year, by the
     * periods' first days, and the total since the site began
     *
     * @return array tydzien, miesiac, rok, and '' for all time
     */
    public function viewsOf($type, $id, $today)
    {
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException(sprintf('No page type "%s"', $type));
        }
        $since = array();
        foreach (array('tydzien', 'miesiac', 'rok') as $period) {
            $since[$period] = self::since($period, $today);
        }
        $rows = $this->_db->fetchAll(
            'SELECT day, views FROM page_views WHERE entity_type = ? AND entity_id = ? AND day >= ?',
            array($type, (int) $id, min($since))
        );
        $views = self::sumSince($rows, $since);
        $total = $this->_db->fetchAll('SELECT viewed FROM ' . self::TYPES[$type] . ' WHERE id = ?', array((int) $id));
        $views[''] = empty($total) ? 0 : (int) $total[0]['viewed'];
        return $views;
    }

    /**
     * Days' rows summed into each period from its first day
     *
     * @param array $rows  of array('day' => Y-m-d, 'views' => n)
     * @param array $since period => its first day
     * @return array period => views
     */
    public static function sumSince(array $rows, array $since)
    {
        $sums = array_fill_keys(array_keys($since), 0);
        foreach ($rows as $row) {
            foreach ($since as $period => $first) {
                if ($row['day'] >= $first) {
                    $sums[$period] += (int) $row['views'];
                }
            }
        }
        return $sums;
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

        $this->_db->query('UPDATE ' . self::TYPES[$type] . ' SET viewed = viewed + 1 WHERE id = ?', array($id));
        $this->_db->upsert(
            'page_views',
            array('entity_type' => $type, 'entity_id' => $id, 'day' => $day, 'views' => 1),
            array('views' => 'page_views.views + 1'),
            array('entity_type', 'day', 'entity_id')
        );
        return true;
    }
}
