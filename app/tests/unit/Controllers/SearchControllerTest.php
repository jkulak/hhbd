<?php

use PHPUnit\Framework\TestCase;

require_once APPLICATION_PATH . '/controllers/SearchController.php';

/**
 * What a search sends the database (#151): the query as typed, not HTML entities, and the same
 * search whatever form its Polish letters arrive in.
 */
class SearchControllerTest extends TestCase
{
    public function testAQueryWithAPolishLetterIsKeptAsTyped(): void
    {
        $this->assertSame('Wzgórze', SearchController::queryOf('Wzgórze'));
    }

    public function testMarkupInAQueryIsKeptForTheViewToEscape(): void
    {
        $this->assertSame('<b>"x" & y</b>', SearchController::queryOf('<b>"x" & y</b>'));
    }

    public function testAQueryFromAnOldLinkInIso88592IsReadAsSuch(): void
    {
        $this->assertSame('Wzgórze Sokół', SearchController::queryOf("Wzg\xF3rze Sok\xF3\xB3"));
    }

    public function testWhitespaceIsTrimmedAndRunsAreOneSpace(): void
    {
        $this->assertSame('Wzgórze Ya-Pa 3', SearchController::queryOf("  Wzgórze \t Ya-Pa\u{00A0}3 \n"));
    }

    public function testAQueryIsAtMostAHundredCharacters(): void
    {
        $this->assertSame(str_repeat('ż', 100), SearchController::queryOf(str_repeat('ż', 150)));
    }

    public function testAnythingButAStringIsAnEmptyQuery(): void
    {
        $this->assertSame('', SearchController::queryOf(array('Wzgórze')));
    }

    public function testCombiningMarksAreLeftOutOfWhatTheDatabaseIsAsked(): void
    {
        $this->assertSame('Sokoł', SearchController::termsOf("Soko\u{0301}ł"));
        $this->assertSame('Sokół', SearchController::termsOf('Sokół'));
    }

    public function testAQueryOfCombiningMarksAloneStaysAsItIs(): void
    {
        $this->assertSame("\u{0301}", SearchController::termsOf("\u{0301}"));
    }

    public function testEverySearchComparesWithoutCaseOrPolishLetters(): void
    {
        $this->assertSame('utf8mb4_uca1400_ai_ci', Jkl_Model_Api::SEARCH_COLLATION);
    }
}
