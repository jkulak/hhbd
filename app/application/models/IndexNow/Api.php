<?php

/**
 * What hhbd.pl tells IndexNow (#220), and when: the pages an import applies, an album an admin
 * publishes and the albums the daily release brings out (#258). Only with a key, INDEXNOW_KEY,
 * which production's compose file alone sets, so nothing local, in CI or in a stack test sends
 * anything. A failure is logged and never stops the import or the publishing that made the
 * pages.
 */
class Model_IndexNow_Api extends Jkl_Model_Api
{
    private static $_instance;

    /** @var Jkl_IndexNow|null */
    private $_client;

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct(?Jkl_IndexNow $client = null)
    {
        parent::__construct();
        if (null === $client) {
            $config = Zend_Registry::get('Config_App');
            $key = isset($config['indexNowKey']) ? (string) $config['indexNowKey'] : '';
            $client = Jkl_IndexNow::isKey($key) ? new Jkl_IndexNow($config['siteUrl'], $key) : null;
        }
        $this->_client = $client;
    }

    /** The key, for the page that serves it; null without one */
    public function key()
    {
        return null === $this->_client ? null : $this->_client->key();
    }

    /** A published album's page and its songs'; nothing for one a visitor cannot open */
    public function albumPaths($albumId)
    {
        if (!Model_Album_Api::getInstance()->isPublished($albumId)) {
            return array();
        }
        $paths = array(Model_Album_Api::getInstance()->find($albumId)->getUrl());
        foreach (Model_Song_Api::getInstance()->getTracklist($albumId)->items as $song) {
            $paths[] = $song->getUrl();
        }
        return $paths;
    }

    /**
     * The pages an applied import created or changed, from its report: each label's, each
     * artist's with something to show, and each album's that is published, with its songs. An
     * artist waiting for its album in the release queue (#258) has nothing to show yet.
     */
    public function importPaths(array $report)
    {
        $paths = array();
        foreach ($report['documents'] as $document) {
            if (!in_array($document['action'], array('created', 'updated'), true) || empty($document['hhbd_id'])) {
                continue;
            }
            if ('album' === $document['entity']) {
                $paths = array_merge($paths, $this->albumPaths($document['hhbd_id']));
            } elseif ('artist' === $document['entity'] && !empty($document['url'])) {
                if (Model_Artist_Api::getInstance()->hasContent($document['hhbd_id'])) {
                    $paths[] = $document['url'];
                }
            } elseif ('label' === $document['entity'] && !empty($document['url'])) {
                $paths[] = $document['url'];
            }
        }
        return array_values(array_unique($paths));
    }

    /**
     * The pages a released album shows or changes (#258): its own and its songs', its artists'
     * and its label's, where a new album now stands
     */
    public function releasePaths($albumId)
    {
        $paths = $this->albumPaths($albumId);
        if (empty($paths)) {
            return array();
        }
        $album = Model_Album_Api::getInstance()->find($albumId);
        foreach ($this->_db->fetchAll('SELECT artistid FROM album_artist_lookup WHERE albumid = ? ORDER BY position, artistid', array((int) $albumId)) as $row) {
            $paths[] = Model_Artist_Api::getInstance()->find($row['artistid'])->getUrl();
        }
        if (!empty($album->label)) {
            $paths[] = $album->label->getUrl();
        }
        return array_values(array_unique($paths));
    }

    /**
     * Sends the paths, and logs what IndexNow said; without a key, or without paths, nothing
     *
     * @param string $why what made the pages, for the log line
     * @return array|null the requests' results, null when nothing was sent
     */
    public function submit(array $paths, $why)
    {
        if (null === $this->_client || empty($paths)) {
            return null;
        }
        try {
            $results = $this->_client->submit($paths);
        } catch (Exception $e) {
            Jkl_Log::write('warn', 'IndexNow was not told of ' . $why, array('logger' => 'indexnow') + Jkl_Log::describe($e));
            return null;
        }
        foreach ($results as $result) {
            $taken = in_array($result['status'], array(200, 202), true);
            Jkl_Log::write($taken ? 'info' : 'warn', sprintf('IndexNow %s %d addresses of %s (HTTP %d)', $taken ? 'took' : 'refused', $result['urls'], $why, $result['status']), array(
                'logger' => 'indexnow',
                'urls'   => $result['urls'],
                'status' => $result['status'],
            ));
        }
        return $results;
    }
}
