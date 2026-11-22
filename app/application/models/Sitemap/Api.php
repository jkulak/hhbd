<?php

/**
 * The pages each sitemap lists (#147), as paths: the sitemaps make them addresses on the origin
 * they are asked on, and IndexNow's first telling on the site's (#220), so both list the same.
 */
class Model_Sitemap_Api extends Jkl_Model_Api
{
    /**
     * The kinds of page with a sitemap, in the index's order. Songs have none (#301): most are a
     * title and the names the album's page shows, and the few worth an index are linked from
     * their albums.
     */
    public const KINDS = array('pages', 'albums', 'artists', 'news', 'labels');

    /** The pages that list others: the lists, the years (#196) and the rankings over each period (#194) */
    public const PAGES = array('/', '/albumy.html', '/wykonawcy.html', '/wytwornie.html', '/premiery.html', '/top10.html');

    /** The protocol's most for one file; the longest list, the artists, has about 4,500 */
    public const LIMIT = 50000;

    /**
     * The kinds whose entries say when their page appeared (#221): an album's when it was
     * published. Every other kind's rows keep no moment their page changed, so their entries
     * have no lastmod.
     */
    public const DATED = array('albums');

    private static $_instance;

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /** @return string[]|null the paths of one kind's pages, null for a kind with no sitemap */
    public function paths($kind)
    {
        $entries = $this->entries($kind);
        return null === $entries ? null : array_column($entries, 0);
    }

    /**
     * @return array[]|null one kind's pages, each as array(path, lastmod), lastmod a day,
     *   YYYY-MM-DD, or null; null for a kind with no sitemap
     */
    public function entries($kind)
    {
        switch ($kind) {
            case 'pages':
                return array_map(function ($path) {
                    return array($path, null);
                }, $this->pages());
            case 'albums':
                // One entry per album, however many artists it credits (#58).
                $list = Model_Album_Api::getInstance()->getAlbumsSitemap(self::LIMIT);
                break;
            case 'news':
                $list = Model_News_Api::getInstance()->getRecent(self::LIMIT, false);
                break;
            case 'artists':
                $list = Model_Artist_Api::getInstance()->getSitemap(self::LIMIT);
                break;
            case 'labels':
                $list = Model_Label_Api::getInstance()->getRecent(self::LIMIT);
                break;
            default:
                return null;
        }
        $dated = in_array($kind, self::DATED, true);
        $entries = array();
        foreach ($list->items as $item) {
            $entries[] = array($item->getUrl(), $dated && null !== $item->published ? substr($item->published, 0, 10) : null);
        }
        return $entries;
    }

    /** The lists, the rankings and the about page */
    private function pages()
    {
        $paths = self::PAGES;
        // What hhbd.pl is, in figures (#218)
        $paths[] = '/o-nas.html';
        // Each year's albums, and the list of the years (#196)
        $paths[] = '/albumy/lata.html';
        foreach (Model_Album_Api::getInstance()->getYears() as $row) {
            $paths[] = '/albumy-' . $row['year'] . '.html';
        }
        foreach (array('/albumy/popularne', '/wykonawcy/popularni') as $base) {
            foreach (array_keys(Model_PageView_Api::PERIODS) as $period) {
                $paths[] = Model_PageView_Api::rankingUrl($base, $period);
            }
        }
        return $paths;
    }
}
