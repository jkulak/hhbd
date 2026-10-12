<?php

use PHPUnit\Framework\TestCase;

/**
 * The proof that a copy into PostgreSQL is MariaDB's data (#233): a checksum of a table's rows
 * that the engines' different order cannot change, and that a changed value, a NULL for an
 * empty string, or a row too many or too few always does.
 */
class Jkl_CopyTest extends TestCase
{
    private const ROWS = array(
        array('1', 'Peja', null, '2016-05-20 00:00:00'),
        array('2', 'Łona', '', '2017-01-01 12:00:00'),
        array('3', "O'Rety \\ \"cytat\"", 'x', null),
    );

    public function testTheOrderOfTheRowsDoesNotCount(): void
    {
        $this->assertSame(Jkl_Copy::checksum(self::ROWS), Jkl_Copy::checksum(array_reverse(self::ROWS)));
    }

    public function testTheCountIsTheRows(): void
    {
        $this->assertSame(3, Jkl_Copy::checksum(self::ROWS)[0]);
    }

    public function testAnIntegerAndItsStringAreTheSameValue(): void
    {
        $asInts = self::ROWS;
        $asInts[0][0] = 1;
        $this->assertSame(Jkl_Copy::checksum(self::ROWS), Jkl_Copy::checksum($asInts));
    }

    /**
     * @dataProvider changes
     */
    public function testAnyChangeChangesTheChecksum(array $rows): void
    {
        $this->assertNotSame(Jkl_Copy::checksum(self::ROWS)[1], Jkl_Copy::checksum($rows)[1]);
    }

    public static function changes(): array
    {
        $value = self::ROWS;
        $value[1][1] = 'Lona';
        $null = self::ROWS;
        $null[1][2] = null;
        $moved = self::ROWS;
        $moved[0] = array('1', null, 'Peja', '2016-05-20 00:00:00');
        $twice = self::ROWS;
        $twice[] = self::ROWS[0];
        return array(
            'a letter' => array($value),
            "'' became NULL" => array($null),
            'a value in another column' => array($moved),
            'a row twice' => array($twice),
            'a row missing' => array(array_slice(self::ROWS, 0, 2)),
        );
    }
}
