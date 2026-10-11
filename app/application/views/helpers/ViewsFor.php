<?php

/**
 * How many times the page a view shows was viewed, for an admin, and a link to its report in
 * Google Analytics (#214); nothing for anyone else. Call as $this->ViewsFor('album', $id,
 * $path), the path the page's canonical address has; it returns HTML, escaped, or ''.
 */
class Zend_View_Helper_ViewsFor extends Zend_View_Helper_Abstract
{
    public function ViewsFor($type, $id, $path)
    {
        if (!$this->view->IsAdmin()) {
            return '';
        }
        $views = Model_PageView_Api::getInstance()->viewsOf($type, $id, date('Y-m-d'));
        $config = Zend_Registry::get('Config_App');
        $property = isset($config['gaPropertyId']) ? (string) $config['gaPropertyId'] : '';
        return self::describe($views, '' === $property ? null : self::reportUrl($property, $path));
    }

    /** The views in words, and the report's link when there is a property to link to */
    public static function describe(array $views, $reportUrl)
    {
        $parts = array();
        foreach (array('tydzien' => 'w 7 dni', 'miesiac' => 'w 30 dni', 'rok' => 'w tym roku', '' => 'od początku') as $period => $label) {
            $parts[] = number_format((int) $views[$period], 0, ',', "\u{00A0}") . ' ' . $label;
        }
        $html = htmlspecialchars(implode(' · ', $parts), ENT_QUOTES, 'UTF-8');
        if (null !== $reportUrl) {
            $html .= ' · <a href="' . htmlspecialchars($reportUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">raport w Google Analytics</a>';
        }
        return $html;
    }

    /**
     * GA4's pages and screens report, its table filtered to the page's path. The report takes
     * its options as one parameter whose value is itself a query string, so the path is encoded
     * once for the inner string and once more with it.
     */
    public static function reportUrl($propertyId, $path)
    {
        $inner = '_u..nav=maui&_r.explorerCard..filterTerm=' . rawurlencode((string) $path);
        return 'https://analytics.google.com/analytics/web/#/p' . rawurlencode((string) $propertyId)
            . '/reports/explorer?params=' . rawurlencode($inner) . '&r=all-pages-and-screens';
    }
}
