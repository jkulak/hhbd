<?php

/**
 * What hhbd.pl tells IndexNow (#220), and when: the pages an import applies and an album an
 * admin publishes. Only with a key, INDEXNOW_KEY, which production's compose file alone sets, so
 * nothing local, in CI or in a stack test sends anything. A failure is logged and never stops
 * the import or the publishing that made the pages.
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
     * The pages an applied import created or changed, from its report: each artist's and
     * label's, and each album's that is published, with its songs
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
            } elseif (in_array($document['entity'], array('artist', 'label'), true) && !empty($document['url'])) {
                $paths[] = $document['url'];
            }
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
