<?php

use PHPUnit\Framework\TestCase;

/**
 * LIKE patterns for values bound as parameters (#230): what a visitor typed is matched as it
 * is, its own % and _ included, and nothing in it is escaped for a query string. And the parts
 * of Jkl_Db's SQL that differ by engine and need no connection (#231).
 */
class Jkl_DbTest extends TestCase
{
    /**
     * @dataProvider containsProvider
     */
    public function testContainsFindsTheTextAnywhereTakingItsWildcardsLiterally(string $input, string $expected): void
    {
        $this->assertSame($expected, Jkl_Db::contains($input));
    }

    public function containsProvider(): array
    {
        return [
            'plain text' => ['peja', '%peja%'],
            'a quote is left as it is, for the parameter to carry' => ["O'Rety", "%O'Rety%"],
            'a double quote too' => ['say "hej"', '%say "hej"%'],
            'a percent sign is no wildcard' => ['100%', '%100\\%%'],
            'nor is an underscore' => ['a_b', '%a\\_b%'],
            'a backslash stands for itself' => ['a\\b', '%a\\\\b%'],
            'empty finds everything' => ['', '%%'],
        ];
    }

    public function testStartsWithFindsValuesBeginningWithTheText(): void
    {
        $this->assertSame('P%', Jkl_Db::startsWith('P'));
        $this->assertSame('\\_%', Jkl_Db::startsWith('_'));
    }

    public function testInOrderKeepsTheRowsInTheOrderOfTheIds(): void
    {
        $this->assertSame('CASE t3.id WHEN 7 THEN 0 WHEN 3 THEN 1 WHEN 12 THEN 2 END', Jkl_Db::inOrder('t3.id', array(7, '3', 12)));
    }

    public function testInOrderOfNoIdsIsTheColumnItself(): void
    {
        $this->assertSame('t3.id', Jkl_Db::inOrder('t3.id', array()));
    }

    /**
     * @dataProvider duplicateKeyProvider
     */
    public function testADuplicateKeyIsKnownOnEitherEngine(Exception $e, bool $duplicate): void
    {
        $this->assertSame($duplicate, Jkl_Db::isDuplicateKey($e));
    }

    public function duplicateKeyProvider(): array
    {
        return [
            "MariaDB's 1062" => [new Zend_Db_Statement_Exception("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '5-7' for key 'u_x'", 23000), true],
            "PostgreSQL's unique_violation" => [new Zend_Db_Statement_Exception('SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "u_x"', 23505), true],
            "MariaDB's NOT NULL, another 23000" => [new Zend_Db_Statement_Exception("SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'name' cannot be null", 23000), false],
            'a syntax error' => [new Zend_Db_Statement_Exception('SQLSTATE[42000]: Syntax error', 42000), false],
        ];
    }
}
