<?php

/**
 * Who added a catalogue row and when, for the admins who see it on the row's page: added, and
 * addedby, an ID in the old users table (database/README.md, "Audit columns").
 */
class Model_Audit_Api extends Jkl_Model_Api
{
    /** Each kind of page that shows it, with its table */
    public const TABLES = array('album' => 'albums', 'artist' => 'artists', 'song' => 'songs', 'label' => 'labels');

    private static $_instance;

    /** @return Model_Audit_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * A row's added and addedby, with the name of who that is, or null for a row that is not
     * there.
     *
     * @return array|null ['added' => 'YYYY-MM-DD HH:MM:SS' or null, 'by' => int, 'name' => string or null]
     */
    public function addedOf($kind, $id)
    {
        if (!isset(self::TABLES[$kind])) {
            return null;
        }
        $rows = $this->_db->fetchAll(
            "SELECT t.added, t.addedby, COALESCE(NULLIF(u.name, ''), u.login) AS name FROM `" . self::TABLES[$kind] . '` t
             LEFT JOIN users u ON u.ID = t.addedby WHERE t.id = ?',
            array((int) $id)
        );
        if (empty($rows)) {
            return null;
        }
        return array(
            'added' => empty($rows[0]['added']) ? null : (string) $rows[0]['added'],
            'by'    => (int) $rows[0]['addedby'],
            'name'  => null === $rows[0]['name'] ? null : (string) $rows[0]['name'],
        );
    }
}
