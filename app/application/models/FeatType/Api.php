<?php

/**
 * The roles a featured artist plays on a song (table feattypes, #66): Rap, Śpiew, Cuty, ...
 *
 * Imported credits name their role; resolve() turns the name into the row's id, adding the role
 * when nobody has used it before. A role name is unique in the table (migration 0013), compared
 * under the table's collation, so "rap" finds "Rap".
 */
class Model_FeatType_Api extends Jkl_Model_Api
{
    /**
     * The role of a credit stored without one. The row with this id has no name; every such
     * credit joins it, so it is never deleted.
     */
    public const UNKNOWN = 0;

    private static $_instance;

    /**
     * @return Model_FeatType_Api
     */
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
     * The id of a role, by name, added when the table does not have it yet. A new role is stored
     * with a capital first letter, as the existing ones are ("Gitara basowa").
     *
     * @return int
     * @throws InvalidArgumentException for an empty name or one longer than 64 characters
     */
    public function resolve($name)
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        if ('' === $name || mb_strlen($name, 'UTF-8') > 64) {
            throw new InvalidArgumentException(sprintf('Not a role: "%s"', $name));
        }

        $rows = $this->_db->fetchAll('SELECT id FROM feattypes WHERE feattype = ?', array($name));
        if (!empty($rows)) {
            return (int) $rows[0]['id'];
        }

        $name = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($name, 1, null, 'UTF-8');
        $this->_db->query('INSERT INTO feattypes (feattype, status) VALUES (?, 999)', array($name));
        return (int) $this->_db->lastInsertId();
    }
}
