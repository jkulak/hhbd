<?php

/**
 * Who added a catalogue row and when, for the admins who see it on the row's page: added, and
 * addedby, an ID in the old users table (database/README.md, "Audit columns"). And which users
 * row an admin's edits are written as (#132).
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
     * @param object|null $db anything with fetchAll(), query() and lastInsertId() taking bound
     *                        values, like Jkl_Db; tests pass a stand-in
     */
    public function __construct($db = null)
    {
        if (null === $db) {
            parent::__construct();
        } else {
            $this->_db = $db;
        }
    }

    /**
     * The users row an hhb_users account's edits are written as, in addedby and updatedby (#132).
     * An admin logs in with hhb_users, while those columns name users, the old table the
     * catalogue's history and the import are in; users.hhb_usr_id links the two (0033). An
     * account without a row gets one the first time, under its display name.
     */
    public function userIdFor($accountId)
    {
        $accountId = (int) $accountId;
        $rows = $this->_db->fetchAll('SELECT ID FROM users WHERE hhb_usr_id = ?', array($accountId));
        if (!empty($rows)) {
            return (int) $rows[0]['ID'];
        }
        $account = $this->_db->fetchAll('SELECT usr_display_name FROM hhb_users WHERE usr_id = ?', array($accountId));
        if (empty($account)) {
            throw new InvalidArgumentException(sprintf('No hhb_users account %d to write an edit as.', $accountId));
        }
        $this->_db->query(
            "INSERT INTO users (login, urlname, added, status, hhb_usr_id) VALUES (?, '', NOW(), 0, ?)",
            array(mb_substr((string) $account[0]['usr_display_name'], 0, 16), $accountId)
        );
        return (int) $this->_db->lastInsertId();
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
