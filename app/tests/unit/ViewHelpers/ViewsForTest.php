<?php

use PHPUnit\Framework\TestCase;

/**
 * A page's views and its Google Analytics report, for an admin (#214).
 */
class Zend_View_Helper_ViewsForTest extends TestCase
{
    public function testThePeriodsReadInOrderWithTheirNumbersGrouped(): void
    {
        $html = Zend_View_Helper_ViewsFor::describe(array('tydzien' => 12, 'miesiac' => 40, 'rok' => 1300, '' => 173433), null);

        $this->assertSame("12 w 7 dni · 40 w 30 dni · 1\u{00A0}300 w tym roku · 173\u{00A0}433 od początku", $html);
    }

    public function testTheReportLinkOpensANewTab(): void
    {
        $html = Zend_View_Helper_ViewsFor::describe(array('tydzien' => 0, 'miesiac' => 0, 'rok' => 0, '' => 0), 'https://analytics.google.com/x?a=1&b=2');

        $this->assertStringContainsString('<a href="https://analytics.google.com/x?a=1&amp;b=2" target="_blank" rel="noopener">raport w Google Analytics</a>', $html);
    }

    public function testTheReportIsThePropertysPagesReportFilteredToThePath(): void
    {
        $this->assertSame(
            'https://analytics.google.com/analytics/web/#/p518385528/reports/explorer?params=_u..nav%3Dmaui%26_r.explorerCard..filterTerm%3D%252Fwdowa-superextra-a535.html&r=all-pages-and-screens',
            Zend_View_Helper_ViewsFor::reportUrl('518385528', '/wdowa-superextra-a535.html')
        );
    }

    public function testDaysAreSummedIntoEachPeriodFromItsFirstDay(): void
    {
        $rows = array(
            array('day' => '2026-10-11', 'views' => '3'),
            array('day' => '2026-10-01', 'views' => '5'),
            array('day' => '2026-02-01', 'views' => '7'),
        );
        $since = array('tydzien' => '2026-10-05', 'miesiac' => '2026-09-12', 'rok' => '2026-01-01');

        $this->assertSame(array('tydzien' => 3, 'miesiac' => 8, 'rok' => 15), Model_PageView_Api::sumSince($rows, $since));
    }
}
