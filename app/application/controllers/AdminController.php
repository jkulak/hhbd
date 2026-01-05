<?php

class AdminController extends Zend_Controller_Action
{
    public function init()
    {
        // Check if user is logged in and is admin
        $auth = Zend_Auth::getInstance();
        if (!$auth->hasIdentity() || $auth->getIdentity()->usr_is_admin !== 'yes') {
            $this->_redirect($this->view->url(array(), 'userNotLoggedIn'));
        }

        $this->view->headMeta()->setName('robots', 'noindex,nofollow');
        $this->view->headTitle()->headTitle('Panel Admina', 'PREPEND');
    }

    public function statsAction()
    {
        $this->view->headTitle()->set('Statystyki - Panel Admina - Hhbd.pl');

        $db = Zend_Db_Table::getDefaultAdapter();

        // Summary Statistics
        $this->view->summaryStats = $this->_getSummaryStats($db);

        // Users Statistics
        $this->view->userStats = $this->_getUserStats($db);

        // Albums Statistics
        $this->view->albumStats = $this->_getAlbumStats($db);

        // Artists Statistics
        $this->view->artistStats = $this->_getArtistStats($db);

        // Songs Statistics
        $this->view->songStats = $this->_getSongStats($db);

        // Labels Statistics
        $this->view->labelStats = $this->_getLabelStats($db);

        // Comments Statistics
        $this->view->commentStats = $this->_getCommentStats($db);

        // Ratings Statistics
        $this->view->ratingStats = $this->_getRatingStats($db);

        // Content Gaps
        $this->view->contentGaps = $this->_getContentGaps($db);

        // Recent Activity
        $this->view->recentActivity = $this->_getRecentActivity($db);
    }

    private function _getSummaryStats($db)
    {
        $stats = array();

        // Albums
        $stats['totalAlbums'] = $db->fetchOne('SELECT COUNT(*) FROM albums WHERE status = 999');
        $stats['albumsWithCovers'] = $db->fetchOne('SELECT COUNT(*) FROM albums WHERE status = 999 AND cover IS NOT NULL AND cover != ""');
        $stats['albumCoverPercentage'] = $stats['totalAlbums'] > 0 ? round(($stats['albumsWithCovers'] / $stats['totalAlbums']) * 100, 1) : 0;

        // Artists
        $stats['totalArtists'] = $db->fetchOne('SELECT COUNT(*) FROM artists WHERE status = 999');
        $stats['artistsWithPhotos'] = $db->fetchOne(
            'SELECT COUNT(*) FROM artists WHERE status = 999 AND id IN (SELECT artistid FROM artists_photos)'
        );
        $stats['artistPhotoPercentage'] = $stats['totalArtists'] > 0 ? round(($stats['artistsWithPhotos'] / $stats['totalArtists']) * 100, 1) : 0;

        // Labels
        $stats['totalLabels'] = $db->fetchOne('SELECT COUNT(*) FROM labels WHERE status = 999');
        $stats['labelsWithLogos'] = $db->fetchOne('SELECT COUNT(*) FROM labels WHERE status = 999 AND logo IS NOT NULL AND logo != ""');
        $stats['labelLogoPercentage'] = $stats['totalLabels'] > 0 ? round(($stats['labelsWithLogos'] / $stats['totalLabels']) * 100, 1) : 0;

        // News
        // News (no status column)
        $stats['totalNews'] = $db->fetchOne('SELECT COUNT(*) FROM news');

        // Comments
        $stats['totalComments'] = $db->fetchOne('SELECT COUNT(*) FROM hhb_comments');

        // Total views
        $albumViews = $db->fetchOne('SELECT SUM(viewed) FROM albums WHERE status = 999') ?: 0;
        $artistViews = $db->fetchOne('SELECT SUM(viewed) FROM artists WHERE status = 999') ?: 0;
        $songViews = $db->fetchOne('SELECT SUM(viewed) FROM songs WHERE status = 999') ?: 0;
        $stats['totalViews'] = $albumViews + $artistViews + $songViews;

        return $stats;
    }

    private function _getUserStats($db)
    {
        $stats = array();

        // Total users
        $stats['total'] = $db->fetchOne('SELECT COUNT(*) FROM hhb_users');

        // Total admins
        $stats['admins'] = $db->fetchOne('SELECT COUNT(*) FROM hhb_users WHERE usr_is_admin = "yes"');

        // Users registered this month
        $stats['thisMonth'] = $db->fetchOne(
            'SELECT COUNT(*) FROM hhb_users WHERE YEAR(usr_added) = YEAR(CURDATE()) AND MONTH(usr_added) = MONTH(CURDATE())'
        );

        // Users registered this year
        $stats['thisYear'] = $db->fetchOne(
            'SELECT COUNT(*) FROM hhb_users WHERE YEAR(usr_added) = YEAR(CURDATE())'
        );

        // Most active users (by login count)
        $stats['mostActive'] = $db->fetchAll(
            'SELECT usr_id, usr_display_name, usr_login_count, usr_last_login 
             FROM hhb_users 
             ORDER BY usr_login_count DESC 
             LIMIT 10'
        );

        // Newest users
        $stats['newest'] = $db->fetchAll(
            'SELECT usr_id, usr_display_name, usr_email, usr_added 
             FROM hhb_users 
             ORDER BY usr_added DESC 
             LIMIT 10'
        );

        return $stats;
    }

    private function _getAlbumStats($db)
    {
        $stats = array();

        // Total albums
        $stats['total'] = $db->fetchOne('SELECT COUNT(*) FROM albums WHERE status = 999');

        // Albums with covers
        $stats['withCovers'] = $db->fetchOne('SELECT COUNT(*) FROM albums WHERE status = 999 AND cover IS NOT NULL AND cover != ""');

        // Albums without covers
        $stats['withoutCovers'] = $stats['total'] - $stats['withCovers'];

        // Albums by year
        $stats['byYear'] = $db->fetchAll(
            'SELECT YEAR(year) as yr, COUNT(*) as count 
             FROM albums 
             WHERE status = 999 AND year IS NOT NULL 
             GROUP BY YEAR(year) 
             ORDER BY yr DESC 
             LIMIT 20'
        );

        // Most viewed albums
        $stats['mostViewed'] = $db->fetchAll(
            'SELECT id, title, viewed 
             FROM albums 
             WHERE status = 999 
             ORDER BY viewed DESC 
             LIMIT 10'
        );

        // Least viewed albums (excluding 0 views)
        $stats['leastViewed'] = $db->fetchAll(
            'SELECT id, title, viewed 
             FROM albums 
             WHERE status = 999 AND viewed > 0 
             ORDER BY viewed ASC 
             LIMIT 10'
        );

        // Albums with 0 views
        $stats['zeroViews'] = $db->fetchOne('SELECT COUNT(*) FROM albums WHERE status = 999 AND viewed = 0');

        // Newest albums
        $stats['newest'] = $db->fetchAll(
            'SELECT id, title, added, addedby 
             FROM albums 
             WHERE status = 999 
             ORDER BY added DESC 
             LIMIT 10'
        );

        return $stats;
    }

    private function _getArtistStats($db)
    {
        $stats = array();

        // Total artists
        $stats['total'] = $db->fetchOne('SELECT COUNT(*) FROM artists WHERE status = 999');

        // Artists with photos
        $stats['withPhotos'] = $db->fetchOne(
            'SELECT COUNT(*) FROM artists WHERE status = 999 AND id IN (SELECT artistid FROM artists_photos)'
        );

        // Artists without photos
        $stats['withoutPhotos'] = $stats['total'] - $stats['withPhotos'];

        // Most viewed artists
        $stats['mostViewed'] = $db->fetchAll(
            'SELECT id, name, viewed 
             FROM artists 
             WHERE status = 999 
             ORDER BY viewed DESC 
             LIMIT 10'
        );

        // Artists with most albums
        $stats['mostAlbums'] = $db->fetchAll(
            'SELECT a.id, a.name, COUNT(DISTINCT aal.albumid) as album_count
             FROM artists a
             JOIN album_artist_lookup aal ON a.id = aal.artistid
             WHERE a.status = 999
             GROUP BY a.id, a.name
             ORDER BY album_count DESC
             LIMIT 10'
        );

        // Male vs Female vs Band
        $stats['byType'] = $db->fetchAll(
            'SELECT type, COUNT(*) as count 
             FROM artists 
             WHERE status = 999 
             GROUP BY type'
        );

        return $stats;
    }

    private function _getSongStats($db)
    {
        $stats = array();

        // Total songs
        $stats['total'] = $db->fetchOne('SELECT COUNT(*) FROM songs WHERE status = 999');

        // Songs with lyrics
        $stats['withLyrics'] = $db->fetchOne('SELECT COUNT(*) FROM songs WHERE status = 999 AND lyrics IS NOT NULL AND lyrics != ""');

        // Songs without lyrics
        $stats['withoutLyrics'] = $stats['total'] - $stats['withLyrics'];

        // Songs with YouTube links
        $stats['withYoutube'] = $db->fetchOne('SELECT COUNT(*) FROM songs WHERE status = 999 AND youtube_url IS NOT NULL AND youtube_url != ""');

        // Most viewed songs
        $stats['mostViewed'] = $db->fetchAll(
            'SELECT id, title, viewed 
             FROM songs 
             WHERE status = 999 
             ORDER BY viewed DESC 
             LIMIT 10'
        );

        // Songs with 0 views
        $stats['zeroViews'] = $db->fetchOne('SELECT COUNT(*) FROM songs WHERE status = 999 AND viewed = 0');

        return $stats;
    }

    private function _getLabelStats($db)
    {
        $stats = array();

        // Total labels
        $stats['total'] = $db->fetchOne('SELECT COUNT(*) FROM labels WHERE status = 999');

        // Labels with logos
        $stats['withLogos'] = $db->fetchOne('SELECT COUNT(*) FROM labels WHERE status = 999 AND logo IS NOT NULL AND logo != ""');

        // Labels with most albums
        $stats['mostAlbums'] = $db->fetchAll(
            'SELECT l.id, l.name, COUNT(a.id) as album_count
             FROM labels l
             LEFT JOIN albums a ON l.id = a.labelid AND a.status = 999
             WHERE l.status = 999
             GROUP BY l.id, l.name
             ORDER BY album_count DESC
             LIMIT 10'
        );

        return $stats;
    }

    private function _getCommentStats($db)
    {
        $stats = array();

        // Total comments
        $stats['total'] = $db->fetchOne('SELECT COUNT(*) FROM hhb_comments');

        // Comments this month
        $stats['thisMonth'] = $db->fetchOne(
            'SELECT COUNT(*) FROM hhb_comments 
             WHERE YEAR(com_added) = YEAR(CURDATE()) AND MONTH(com_added) = MONTH(CURDATE())'
        );

        // Most commented albums
        $stats['mostCommentedAlbums'] = $db->fetchAll(
            'SELECT a.id, a.title, COUNT(c.com_id) as comment_count
             FROM albums a
             JOIN hhb_comments c ON a.id = c.com_object_id AND c.com_object_type = "a"
             WHERE a.status = 999
             GROUP BY a.id, a.title
             ORDER BY comment_count DESC
             LIMIT 10'
        );

        // Most active commenters
        $stats['mostActiveCommenters'] = $db->fetchAll(
            'SELECT u.usr_id, u.usr_display_name, COUNT(c.com_id) as comment_count
             FROM hhb_users u
             JOIN hhb_comments c ON u.usr_id = c.com_author_id
             GROUP BY u.usr_id, u.usr_display_name
             ORDER BY comment_count DESC
             LIMIT 10'
        );

        return $stats;
    }

    private function _getRatingStats($db)
    {
        $stats = array();

        // Total album ratings
        $stats['totalAlbum'] = $db->fetchOne('SELECT COUNT(*) FROM ratings');

        // Average rating count per album
        $stats['avgRatingsPerAlbum'] = $db->fetchOne(
            'SELECT AVG(rating_count) FROM (
                SELECT COUNT(*) as rating_count 
                FROM ratings 
                GROUP BY albumid
            ) as subq'
        );

        // Highest rated albums (minimum 5 ratings)
        $stats['highestRatedAlbums'] = $db->fetchAll(
            'SELECT ra.albumid, a.title, ra.rating, 
                    (SELECT COUNT(*) FROM ratings r WHERE r.albumid = ra.albumid) as howmany
             FROM ratings_avg ra
             JOIN albums a ON ra.albumid = a.id
             WHERE a.status = 999
             HAVING howmany >= 5
             ORDER BY ra.rating DESC
             LIMIT 10'
        );

        // Lowest rated albums (minimum 5 ratings)
        $stats['lowestRatedAlbums'] = $db->fetchAll(
            'SELECT ra.albumid, a.title, ra.rating,
                    (SELECT COUNT(*) FROM ratings r WHERE r.albumid = ra.albumid) as howmany
             FROM ratings_avg ra
             JOIN albums a ON ra.albumid = a.id
             WHERE a.status = 999
             HAVING howmany >= 5
             ORDER BY ra.rating ASC
             LIMIT 10'
        );

        // Most rated albums
        $stats['mostRatedAlbums'] = $db->fetchAll(
            'SELECT r.albumid, a.title, ra.rating, COUNT(*) as howmany
             FROM ratings r
             JOIN albums a ON r.albumid = a.id
             LEFT JOIN ratings_avg ra ON r.albumid = ra.albumid
             WHERE a.status = 999
             GROUP BY r.albumid, a.title, ra.rating
             ORDER BY howmany DESC
             LIMIT 10'
        );

        return $stats;
    }

    private function _getContentGaps($db)
    {
        $gaps = array();

        // Albums without any songs
        $gaps['albumsWithoutSongs'] = $db->fetchOne(
            'SELECT COUNT(*) FROM albums a 
             WHERE a.status = 999 
             AND a.id NOT IN (SELECT DISTINCT albumid FROM album_lookup)'
        );

        // Artists without albums
        $gaps['artistsWithoutAlbums'] = $db->fetchOne(
            'SELECT COUNT(*) FROM artists a 
             WHERE a.status = 999 
             AND a.id NOT IN (SELECT DISTINCT artistid FROM album_artist_lookup)'
        );

        // Albums without comments
        $gaps['albumsWithoutComments'] = $db->fetchOne(
            'SELECT COUNT(*) FROM albums a 
             WHERE a.status = 999 
             AND a.id NOT IN (SELECT DISTINCT com_object_id FROM hhb_comments WHERE com_object_type = "a")'
        );

        // Albums without ratings
        $gaps['albumsWithoutRatings'] = $db->fetchOne(
            'SELECT COUNT(*) FROM albums a 
             WHERE a.status = 999 
             AND a.id NOT IN (SELECT DISTINCT albumid FROM ratings)'
        );

        return $gaps;
    }

    private function _getRecentActivity($db)
    {
        $activity = array();

        // Recent comments
        $activity['recentComments'] = $db->fetchAll(
            'SELECT c.com_object_type, c.com_object_id, c.com_content, c.com_added, u.usr_id, u.usr_display_name
             FROM hhb_comments c
             JOIN hhb_users u ON c.com_author_id = u.usr_id
             ORDER BY c.com_added DESC
             LIMIT 10'
        );

        // Recent ratings
        $activity['recentRatings'] = $db->fetchAll(
            'SELECT r.albumid, r.rate, r.added, u.usr_id, u.usr_display_name, a.title
             FROM ratings r
             LEFT JOIN hhb_users u ON r.userid = u.usr_id
             JOIN albums a ON r.albumid = a.id
             WHERE a.status = 999
             ORDER BY r.added DESC
             LIMIT 10'
        );

        return $activity;
    }
}
