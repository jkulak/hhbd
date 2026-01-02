<?php

use PHPUnit\Framework\TestCase;

class Jkl_Tools_DateTest extends TestCase
{
    /**
     * @dataProvider normalDateProvider
     */
    public function testGetNormalDate(string $input, string $expected): void
    {
        $result = Jkl_Tools_Date::getNormalDate($input);
        $this->assertEquals($expected, $result);
    }

    public function normalDateProvider(): array
    {
        return [
            'full date January' => [
                '2020-01-15',
                '15 stycznia 2020'
            ],
            'full date February' => [
                '2020-02-28',
                '28 lutego 2020'
            ],
            'full date March' => [
                '2020-03-01',
                '1 marca 2020'
            ],
            'full date April' => [
                '2020-04-10',
                '10 kwietnia 2020'
            ],
            'full date May' => [
                '2020-05-05',
                '5 maja 2020'
            ],
            'full date June' => [
                '2020-06-21',
                '21 czerwca 2020'
            ],
            'full date July' => [
                '2020-07-04',
                '4 lipca 2020'
            ],
            'full date August' => [
                '2020-08-15',
                '15 sierpnia 2020'
            ],
            'full date September' => [
                '2020-09-30',
                '30 września 2020'
            ],
            'full date October' => [
                '2020-10-31',
                '31 października 2020'
            ],
            'full date November' => [
                '2020-11-11',
                '11 listopada 2020'
            ],
            'full date December' => [
                '2020-12-25',
                '25 grudnia 2020'
            ],
            'year only (month 00)' => [
                '2020-00-00',
                '2020'
            ],
            'unknown day' => [
                '2020-05-00',
                'któregoś maja 2020'
            ],
        ];
    }

    public function testMonthsArrayContainsAllMonths(): void
    {
        $this->assertCount(12, Jkl_Tools_Date::$months);
        $this->assertEquals('stycznia', Jkl_Tools_Date::$months[1]);
        $this->assertEquals('grudnia', Jkl_Tools_Date::$months[12]);
    }
}
