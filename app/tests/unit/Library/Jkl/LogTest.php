<?php

use PHPUnit\Framework\TestCase;

/**
 * One log line in the shared host's format (#101; CONTRACT.md §9 in gcloud-ovh-migrate).
 */
class Jkl_LogTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_REQUEST_ID']);
    }

    public function testALineIsOneJsonObjectWithTimeInUtcToTheMillisecondLevelAndMsg(): void
    {
        $line = Jkl_Log::line('info', 'Page served', array('logger' => 'app'), 1791551776.8235);

        $this->assertStringNotContainsString("\n", $line);
        $this->assertSame(array('time' => '2026-10-09T13:16:16.823Z', 'level' => 'info', 'msg' => 'Page served', 'logger' => 'app'), json_decode($line, true));
    }

    public function testZendPrioritiesBecomeTheFourLevels(): void
    {
        $this->assertSame(
            array('error', 'error', 'error', 'error', 'warn', 'info', 'info', 'debug'),
            array_map(array('Jkl_Log', 'levelOf'), range(0, 7))
        );
    }

    public function testALineWrittenForARequestCarriesTheEdgesRequestId(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = '4b0c4b9e-3f6a-4d6c-9a1e-2d7f0e1c5a11';

        $this->assertSame('4b0c4b9e-3f6a-4d6c-9a1e-2d7f0e1c5a11', json_decode(Jkl_Log::line('info', 'x'), true)['request_id']);
    }

    public function testARequestIdThatLooksLikeNoneIsLeftOut(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = "evil\nline";

        $this->assertArrayNotHasKey('request_id', json_decode(Jkl_Log::line('info', 'x'), true));
    }

    public function testALongStackIsCutSoTheLineStaysUnderSixteenKilobytes(): void
    {
        $line = Jkl_Log::line('error', 'Request failed', array('stack' => str_repeat("#0 frame\n", 5000)));

        $this->assertLessThanOrEqual(Jkl_Log::LINE_LIMIT, strlen($line));
        $this->assertStringEndsWith(' [cut]', json_decode($line, true)['stack']);
    }

    public function testAnExceptionIsItsClassAndMessageAndOneFieldOfFrames(): void
    {
        $described = Jkl_Log::describe(new RuntimeException('SQLSTATE[HY000] [2002] No such host'));

        $this->assertSame('RuntimeException: SQLSTATE[HY000] [2002] No such host', $described['error']);
        $this->assertStringContainsString('LogTest.php', $described['stack']);
    }

    public function testTheZendFormatterWritesAnEventAsALineWithItsExtras(): void
    {
        $formatter = Jkl_Log_Formatter_Json::factory(array());
        $line = $formatter->format(array(
            'timestamp' => '2026-10-09T15:00:00+02:00', 'message' => 'Request failed', 'priority' => Zend_Log::ERR,
            'priorityName' => 'ERR', 'path' => '/albumy.html', 'status' => 500, 'exception' => new RuntimeException('down'),
        ));
        $o = json_decode($line, true);

        $this->assertStringEndsWith("\n", $line);
        $this->assertSame(array('error', 'Request failed', 'app', '/albumy.html', 500, 'RuntimeException: down'), array($o['level'], $o['msg'], $o['logger'], $o['path'], $o['status'], $o['error']));
        $this->assertArrayNotHasKey('priorityName', $o);
        $this->assertArrayNotHasKey('exception', $o);
    }
}
