<?php

#[\AllowDynamicProperties]
class SearchController extends Zend_Controller_Action
{
    public function init()
    {
        $this->view->headMeta()->setName('keywords', 'polski hip-hop, albumy');
        $this->view->headTitle()->headTitle('Wyniki wyszukiwania', 'PREPEND');
        $this->view->headMeta()->setName('description', 'Wyniki wyszukiwania www.hhbd.pl');
        // Each query would be a page of its own to index, thin and like the pages it lists; a
        // crawler still follows the links to those (#147).
        $this->view->headMeta()->setName('robots', 'noindex,follow');
        $this->params = $this->getRequest()->getParams();
    }

    public function indexAction()
    {
        $searchQuery = self::queryOf(isset($this->params['q']) ? $this->params['q'] : '');
        if ('' === $searchQuery) {
            $searchQuery = 'niczego?';
        }
        $terms = self::termsOf($searchQuery);
        $type = (!empty($this->params['tp'])) ? $this->params['tp'] : null;

        if (isset($type)) {
            if (!in_array($type, array('album', 'wykonawca', 'utwor', 'wytwornia'))) {
                $type = null;
            }
        }

        $page = (!empty($this->params['page'])) ? $this->params['page'] : 1;
        $limit = (!empty($type) ? 12 : 4);

        // search artists (names and nicknames)
        if (!isset($type) or $type == 'wykonawca') {
            $artists = Model_Artist_Api::getInstance()->getLike($terms, $limit, $page);
            $nicknames = Model_Artist_Api::getInstance()->getNicknamesLike($terms, $limit, $page);
            // One entry per artist, found by name or by nickname; by id, since two artists may
            // share a name (#102), and those two show their qualifiers.
            $byId = array();
            foreach (array_merge($artists->items, $nicknames->items) as $artist) {
                $byId += array($artist->id => $artist);
            }
            $resultArtists = new Jkl_List();
            $resultArtists->items = array_slice(array_values($byId), 0, $limit);
            Model_Artist_Container::qualifyNamesakes($resultArtists);
            $this->view->resultArtists = $resultArtists;
        }

        // search album titles
        if (!isset($type) or $type == 'album') {
            $resultAlbums = Model_Album_Api::getInstance()->getLike($terms, $limit, $page);
            $this->view->resultAlbums = $resultAlbums;
        }

        // search song names
        if (!isset($type) or $type == 'utwor') {
            $limit = (!empty($type) ? 24 : 4);
            $resultSongs = Model_Song_Api::getInstance()->getLike($terms, $limit, $page);
            $this->view->resultSongs = $resultSongs;
        }

        // search label names
        if (!isset($type) or $type == 'wytwornia') {
            $resultLabels = Model_Label_Api::getInstance()->getLike($terms, $limit, $page);
            $this->view->resultLabels = $resultLabels;
        }

        $totalArtistCount = Model_Artist_Api::getInstance()->getLikeCount($terms);
        $totalAlbumCount = Model_Album_Api::getInstance()->getLikeCount($terms);
        $totalSongCount = Model_Song_Api::getInstance()->getLikeCount($terms);
        $totalLabelCount = Model_Label_Api::getInstance()->getLikeCount($terms);

        // need to bulid paginator per each type
        if (isset($type)) {
            switch ($type) {
                case 'wykonawca':
                    $totalCount = $totalArtistCount;
                    $itemsPerPage = 12;
                    $totalResults = sizeof($resultArtists->items);
                    break;
                case 'album':
                    $totalCount = $totalAlbumCount;
                    $itemsPerPage = 12;
                    $totalResults = sizeof($resultAlbums->items);
                    break;
                case 'utwor':
                    $totalCount = $totalSongCount;
                    $itemsPerPage = 24;
                    $totalResults = sizeof($resultSongs->items);
                    break;
                case 'wytwornia':
                    $totalCount = $totalLabelCount;
                    $itemsPerPage = 12;
                    $totalResults = sizeof($resultLabels->items);
                    break;
                default:
                    $itemsPerPage = 12;
                    break;
            }
            // we have detailed search
            if ($totalCount > $itemsPerPage) {
                $paginator = Zend_Paginator::factory($totalCount);
                $paginator->setCurrentPageNumber($page);
                $paginator->setItemCountPerPage($itemsPerPage);
                $paginator->setPageRange(15);
                $paginator->type = $type;
                $paginator->searchQuery = $searchQuery;
                Zend_View_Helper_PaginationControl::setDefaultViewPartial('search/_paginator.phtml');
                $this->view->paginator = $paginator;
                // print_r($paginator);
            }
            $this->view->totalCount = $totalCount;
            $this->view->totalResults = $totalResults;
        } else {
            $this->view->totalCount = sizeof($resultArtists->items) + sizeof($resultAlbums->items) + sizeof($resultSongs->items) + sizeof($resultLabels->items);
        }

        // search lyrics ???
        $this->view->type = $type;
        $this->view->searchQuery = $searchQuery;

        $this->view->totalArtistCount = $totalArtistCount;
        $this->view->totalAlbumCount = $totalAlbumCount;
        $this->view->totalSongCount = $totalSongCount;
        $this->view->totalLabelCount = $totalLabelCount;

        // The search and what it found, for analytics (#161)
        $this->view->Analytics()->page('search')
            ->set('search_term', $searchQuery)
            ->set('search_results', $totalArtistCount + $totalAlbumCount + $totalSongCount + $totalLabelCount);

        $this->view->recentSearches = Model_Search_Api::getInstance()->getRecent();
        $this->view->mostPopularSearches = Model_Search_Api::getInstance()->getMostPopular();

        if ($this->view->totalCount > 0) {
            Model_Search_Api::getInstance()->saveSearch($searchQuery);
        }

        $this->view->headMeta()->setName('keywords', $searchQuery . ',wyniki,wyszukiwania,polski hip-hop,albumy,wykonawcy,wytwórnie,utwory,teksty,teledyski');
        $this->view->headTitle()->headTitle('Wyniki wyszukiwania ' .  $searchQuery . ' na największej stronie o polskim hip-hopie!', 'PREPEND');
        $this->view->headMeta()->setName('description', 'Wyniki wyszukiwania "' .  $searchQuery . '" w www.hhbd.pl');
    }

    /**
     * The query as typed, for the database (#151): it went through htmlentities() before, so
     * "Wzgórze" was searched as "Wzg&oacute;rze" and found nothing. The views escape it where
     * they show it. One that is not UTF-8 comes from an old link in ISO-8859-2 ("Wzg%F3rze"),
     * which htmlentities() turned into nothing, and nothing matched every row. Whitespace runs
     * are one space, and 100 characters are plenty for a name.
     */
    public static function queryOf($raw)
    {
        $query = is_string($raw) ? $raw : '';
        if (!mb_check_encoding($query, 'UTF-8')) {
            $query = mb_convert_encoding($query, 'UTF-8', 'ISO-8859-2');
        }
        $query = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $query));
        return mb_substr($query, 0, 100, 'UTF-8');
    }

    /**
     * What the database is asked for: the query without combining marks. "ó" typed as "o" and
     * a combining acute, as some systems send it, is two characters to LIKE, which the accent
     * does not let match; the collation ignores accents anyway (#151).
     */
    public static function termsOf($query)
    {
        $terms = (string) preg_replace('/\p{Mn}+/u', '', $query);
        return '' === $terms ? $query : $terms;
    }
}
