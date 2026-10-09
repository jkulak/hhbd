<?php

use PHPUnit\Framework\TestCase;

/**
 * Who added a row and when, in the words an admin reads on its page.
 */
class Zend_View_Helper_AddedForTest extends TestCase
{
    public function testAPersonIsNamedWithTheTimeToTheMinute(): void
    {
        $this->assertSame(
            '12 maja 2009, 14:03 (Redakcja Testowa)',
            Zend_View_Helper_AddedFor::describe(array('added' => '2009-05-12 14:03:59', 'by' => 7, 'name' => 'Redakcja Testowa'), 1100)
        );
    }

    public function testTheImportIsNamedAsSuch(): void
    {
        $this->assertSame(
            '9 października 2026, 12:00 (import)',
            Zend_View_Helper_AddedFor::describe(array('added' => '2026-10-09 12:00:00', 'by' => 1100, 'name' => 'Import'), 1100)
        );
    }

    public function testNeitherWhenNorWhoKnown(): void
    {
        $this->assertSame('data nieznana (autor nieznany)', Zend_View_Helper_AddedFor::describe(array('added' => null, 'by' => 0, 'name' => null), 1100));
        $this->assertSame('data nieznana (autor nieznany)', Zend_View_Helper_AddedFor::describe(array('added' => '0000-00-00 00:00:00', 'by' => 0, 'name' => null), 1100));
    }

    public function testAnAuthorNoLongerInTheUsersTableIsShownByNumber(): void
    {
        $this->assertSame('1 stycznia 2005, 08:00 (użytkownik nr 42)', Zend_View_Helper_AddedFor::describe(array('added' => '2005-01-01 08:00:00', 'by' => 42, 'name' => null), 1100));
    }

    public function testANameIsEscaped(): void
    {
        $this->assertStringEndsWith('(&lt;b&gt;Kuba&lt;/b&gt;)', Zend_View_Helper_AddedFor::describe(array('added' => '2005-01-01 08:00:00', 'by' => 3, 'name' => '<b>Kuba</b>'), 1100));
    }
}
