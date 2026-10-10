<?php

use PHPUnit\Framework\TestCase;

/**
 * What counts as a page view (#68): a GET from a browser, not from an admin; a crawler, a link
 * preview, a script or a HEAD request counts for nothing.
 */
class Model_PageView_ApiTest extends TestCase
{
    /**
     * @dataProvider browsers
     */
    public function testABrowsersGetCounts(string $agent): void
    {
        $this->assertTrue(Model_PageView_Api::counts('GET', $agent, false));
    }

    public static function browsers(): array
    {
        return array(
            'Chrome on a Mac'    => array('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36'),
            'Firefox on Windows' => array('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0'),
            'Safari on a phone'  => array('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'),
        );
    }

    /**
     * @dataProvider robots
     */
    public function testACrawlerAPreviewOrAScriptDoesNotCount(string $agent): void
    {
        $this->assertFalse(Model_PageView_Api::counts('GET', $agent, false));
    }

    public static function robots(): array
    {
        return array(
            'Googlebot'         => array('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'),
            'AdSense'           => array('Mediapartners-Google'),
            'Google inspection' => array('Mozilla/5.0 (compatible; Google-InspectionTool/1.0)'),
            'Bing'              => array('Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'),
            'Facebook preview'  => array('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'),
            'an AI crawler'     => array('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.2; +https://openai.com/gptbot'),
            'curl'              => array('curl/8.7.1'),
            'Python'            => array('python-requests/2.32.3'),
            'a headless Chrome' => array('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0 Safari/537.36'),
            'no agent at all'   => array(''),
        );
    }

    public function testOnlyAGetCounts(): void
    {
        $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36';
        $this->assertFalse(Model_PageView_Api::counts('HEAD', $chrome, false));
        $this->assertFalse(Model_PageView_Api::counts('POST', $chrome, false));
        $this->assertTrue(Model_PageView_Api::counts('get', $chrome, false));
    }

    /**
     * @dataProvider periods
     */
    public function testAPeriodStartsTheRightDayAndIncludesToday(string $period, string $today, ?string $since): void
    {
        $this->assertSame($since, Model_PageView_Api::since($period, $today));
    }

    public static function periods(): array
    {
        return array(
            'the last 7 days'            => array('tydzien', '2026-10-11', '2026-10-05'),
            'across a month'             => array('tydzien', '2026-03-03', '2026-02-25'),
            'the last 30 days'           => array('miesiac', '2026-10-11', '2026-09-12'),
            'across a year'              => array('miesiac', '2027-01-10', '2026-12-12'),
            'this year'                  => array('rok', '2026-10-11', '2026-01-01'),
            'this year, on its first day' => array('rok', '2027-01-01', '2027-01-01'),
            'all time'                   => array('', '2026-10-11', null),
        );
    }

    public function testThereIsNoOtherPeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Model_PageView_Api::since('dekada', '2026-10-11');
    }

    public function testARankingsAddressEndsInItsPeriod(): void
    {
        $this->assertSame('/albumy/popularne-tydzien.html', Model_PageView_Api::rankingUrl('/albumy/popularne', 'tydzien'));
        $this->assertSame('/albumy/popularne.html', Model_PageView_Api::rankingUrl('/albumy/popularne', ''));
    }

    public function testAnAdminsViewDoesNotCount(): void
    {
        $this->assertFalse(Model_PageView_Api::counts('GET', 'Mozilla/5.0 (Macintosh) Firefox/131.0', true));
    }
}
