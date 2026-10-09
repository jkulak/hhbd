<?php

/**
 * The addresses hhbd had before the .html ones (#26), and the pages they lead to now. The old
 * site named a page by a slug its tools kept in urlname (backoffice/admin/TOOLS on the branch
 * backoffice-archive): /n/peja an artist, /a/ an album, /l/ a label, /s/ a song, and later
 * /wykonawca/, /album/ and /wytwornia/ the same way. The importer writes the same column with
 * dashes where the old tools wrote underscores, so the two count as one.
 */
class Model_Legacy_Api extends Jkl_Model_Api
{
    /** Each kind of old address, with the table its slug is in */
    public const TABLES = array('artist' => 'artists', 'album' => 'albums', 'label' => 'labels', 'song' => 'songs');

    private static $_instance;

    /** @return Model_Legacy_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * The ids whose slug is the one given, two at most: one is the page, two mean the slug does
     * not tell which (songs share theirs often, "intro"). Case does not count, as the old links
     * were written both ways.
     *
     * @return int[]
     */
    public function idsBySlug($kind, $slug)
    {
        $slug = trim((string) $slug);
        if (!isset(self::TABLES[$kind]) || '' === $slug) {
            return array();
        }
        $rows = $this->_db->fetchAll(
            'SELECT id FROM `' . self::TABLES[$kind] . "` WHERE urlname <> '' AND REPLACE(urlname, '-', '_') = REPLACE(?, '-', '_') ORDER BY id LIMIT 2",
            array($slug)
        );
        return array_map('intval', array_column($rows, 'id'));
    }

    /** The page of a kind's row, or null when the row is gone */
    public function urlOf($kind, $id)
    {
        switch ($kind) {
            case 'artist':
                $row = Model_Artist_Api::getInstance()->find($id);
                break;
            case 'album':
                $row = Model_Album_Api::getInstance()->find($id);
                break;
            case 'label':
                $row = Model_Label_Api::getInstance()->find($id);
                break;
            case 'song':
                $row = Model_Song_Api::getInstance()->find($id);
                break;
            default:
                $row = null;
        }
        return null === $row ? null : $row->getUrl();
    }

    /** The page of a news item by its id, or null when there is none (/news/1877) */
    public function newsUrl($id)
    {
        $rows = $this->_db->fetchAll('SELECT id FROM news WHERE id = ?', array((int) $id));
        return empty($rows) ? null : Model_News_Api::getInstance()->find($rows[0]['id'])->getUrl();
    }

    /** The search an old slug that names no single row falls back to: its words */
    public static function searchUrl($slug)
    {
        $words = trim(preg_replace('/\s+/', ' ', str_replace(array('_', '-'), ' ', (string) $slug)));
        return '/szukaj.html?q=' . urlencode($words);
    }
}
