<?php

use PHPUnit\Framework\TestCase;

class Jkl_Tools_StringTest extends TestCase
{
    /**
     * @dataProvider wordSplitProvider
     */
    public function testWordSplit(string $input, int $words, string $expected): void
    {
        $result = Jkl_Tools_String::word_split($input, $words);
        $this->assertEquals($expected, $result);
    }

    public function wordSplitProvider(): array
    {
        return [
            'basic split' => [
                'one two three four five',
                3,
                'one two three'
            ],
            'fewer words than limit' => [
                'one two',
                5,
                'one two'
            ],
            'exact word count' => [
                'one two three',
                3,
                'one two three'
            ],
            'single word' => [
                'word',
                1,
                'word'
            ],
            'multiple spaces' => [
                'one   two    three',
                2,
                'one two'
            ],
            'empty string' => [
                '',
                5,
                ''
            ],
            'default 15 words' => [
                'a b c d e f g h i j k l m n o p q r',
                15,
                'a b c d e f g h i j k l m n o'
            ],
        ];
    }

    /**
     * @dataProvider trimStrProvider
     */
    public function testTrimStr(string $input, int $len, bool $addDots, string $expected): void
    {
        $result = Jkl_Tools_String::trim_str($input, $len, $addDots);
        $this->assertEquals($expected, $result);
    }

    public function trimStrProvider(): array
    {
        return [
            'short string with dots' => [
                'hello',
                10,
                true,
                'hello...'
            ],
            'short string without dots' => [
                'hello',
                10,
                false,
                'hello'
            ],
            'long string truncated with dots' => [
                'this is a very long string that needs truncation',
                10,
                true,
                'this is a ver...'  // Cuts at first space AFTER position 10
            ],
            'long string truncated without dots' => [
                'this is a very long string that needs truncation',
                10,
                false,
                'this is a ver'  // Cuts at first space AFTER position 10
            ],
            'string with no space after cutoff' => [
                'exactly ten',
                10,
                true,
                'exactly te...'  // No space found after position 10, cuts at -1
            ],
            'string shorter than limit with dots' => [
                'short',
                20,
                true,
                'short...'
            ],
            'string equal to limit' => [
                'exactly ten',
                11,
                false,
                'exactly ten'  // Length is exactly 11, so no truncation
            ],
        ];
    }
}
