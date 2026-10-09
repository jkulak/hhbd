<?php

/**
 * Who added the row a page shows and when, for an admin, and nothing for anyone else:
 * "12 maja 2009, 14:03 (Kuba)". Call as $this->AddedFor('album', $id) in a view; it returns
 * HTML, escaped, or an empty string.
 */
class Zend_View_Helper_AddedFor extends Zend_View_Helper_Abstract
{
    public function AddedFor($kind, $id)
    {
        if (!$this->view->IsAdmin()) {
            return '';
        }
        $row = Model_Audit_Api::getInstance()->addedOf($kind, $id);
        if (null === $row) {
            return '';
        }
        try {
            $import = Model_Provenance_Api::getInstance()->getImportUserId();
        } catch (RuntimeException $e) {
            $import = Model_Provenance_Api::IMPORT_USER_ID;
        }
        return self::describe($row, $import);
    }

    /**
     * The words for a row's added and addedby: when, to the minute as stored, and who in
     * brackets, the import and an unknown author by those names.
     */
    public static function describe(array $row, $importUserId)
    {
        $added = (string) $row['added'];
        if ('' === $added || 0 === strpos($added, '0000')) {
            $when = 'data nieznana';
        } else {
            $when = (int) substr($added, 8, 2) . ' ' . Jkl_Tools_Date::$months[(int) substr($added, 5, 2)] . ' '
                . substr($added, 0, 4) . ', ' . substr($added, 11, 5);
        }
        if ((int) $importUserId === $row['by']) {
            $who = 'import';
        } elseif (0 === $row['by']) {
            $who = 'autor nieznany';
        } elseif (null === $row['name']) {
            $who = 'użytkownik nr ' . $row['by'];
        } else {
            $who = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        }
        return $when . ' (' . $who . ')';
    }
}
