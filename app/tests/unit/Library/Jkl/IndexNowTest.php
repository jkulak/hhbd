<?php

use PHPUnit\Framework\TestCase;

/**
 * IndexNow (#220): addresses on the site's own host, each once, at most 10,000 to a request,
 * with the key and where the site serves it.
 */
class Jkl_IndexNowTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef';

    public function testARequestNamesTheHostTheKeyAndWhereTheKeyIs(): void
    {
        $requests = (new Jkl_IndexNow('https://hhbd.pl', self::KEY))->requests(array('/wdowa-superextra-a535.html'));

        $this->assertSame(array(array(
            'host'        => 'hhbd.pl',
            'key'         => self::KEY,
            'keyLocation' => 'https://hhbd.pl/' . self::KEY . '.txt',
            'urlList'     => array('https://hhbd.pl/wdowa-superextra-a535.html'),
        )), $requests);
    }

    public function testEachAddressIsSentOnceAndOnlyTheSitesOwn(): void
    {
        $requests = (new Jkl_IndexNow('https://hhbd.pl/', self::KEY))->requests(array(
            '/mes-p35.html', 'https://hhbd.pl/mes-p35.html', 'https://example.com/x.html', '', '/pogoda-s7329.html',
        ));

        $this->assertSame(array('https://hhbd.pl/mes-p35.html', 'https://hhbd.pl/pogoda-s7329.html'), $requests[0]['urlList']);
    }

    public function testMoreThanABatchIsSplitIntoRequests(): void
    {
        $paths = array();
        for ($i = 1; $i <= Jkl_IndexNow::BATCH + 1; $i++) {
            $paths[] = '/x-s' . $i . '.html';
        }

        $requests = (new Jkl_IndexNow('https://hhbd.pl', self::KEY))->requests($paths);

        $this->assertCount(2, $requests);
        $this->assertCount(Jkl_IndexNow::BATCH, $requests[0]['urlList']);
        $this->assertSame(array('https://hhbd.pl/x-s10001.html'), $requests[1]['urlList']);
    }

    public function testNoAddressesSendNothing(): void
    {
        $sent = 0;
        $indexNow = new Jkl_IndexNow('https://hhbd.pl', self::KEY, function () use (&$sent) {
            $sent++;
            return 200;
        });

        $this->assertSame(array(), $indexNow->submit(array()));
        $this->assertSame(0, $sent);
    }

    public function testEachRequestIsPostedAsJsonToIndexNowAndItsStatusReported(): void
    {
        $posted = array();
        $indexNow = new Jkl_IndexNow('https://hhbd.pl', self::KEY, function ($endpoint, $json) use (&$posted) {
            $posted[] = array($endpoint, json_decode($json, true));
            return 202;
        });

        $results = $indexNow->submit(array('/mes-p35.html', '/pogoda-s7329.html'));

        $this->assertSame(array(array('urls' => 2, 'status' => 202)), $results);
        $this->assertSame(Jkl_IndexNow::ENDPOINT, $posted[0][0]);
        $this->assertSame('https://hhbd.pl/mes-p35.html', $posted[0][1]['urlList'][0]);
    }

    /**
     * @dataProvider keys
     */
    public function testAKeyIsEightToAHundredAndTwentyEightLettersDigitsAndDashes(string $key, bool $valid): void
    {
        $this->assertSame($valid, Jkl_IndexNow::isKey($key));
    }

    public static function keys(): array
    {
        return array(
            'hex' => array(self::KEY, true),
            'with dashes' => array('abcd-1234', true),
            'too short' => array('abc1234', false),
            'too long' => array(str_repeat('a', 129), false),
            'a dot' => array('abcd.1234', false),
            'empty' => array('', false),
        );
    }

    public function testABadKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Jkl_IndexNow('https://hhbd.pl', 'short');
    }
}
