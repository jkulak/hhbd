<?php

use PHPUnit\Framework\TestCase;

/**
 * LIKE patterns for values bound as parameters (#230): what a visitor typed is matched as it
 * is, its own % and _ included, and nothing in it is escaped for a query string.
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
}
