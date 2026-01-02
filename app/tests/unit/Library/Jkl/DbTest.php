<?php

use PHPUnit\Framework\TestCase;

class Jkl_DbTest extends TestCase
{
    /**
     * @dataProvider escapeProvider
     */
    public function testEscape(string $input, string $expected): void
    {
        $result = Jkl_Db::escape($input);
        $this->assertEquals($expected, $result);
    }

    public function escapeProvider(): array
    {
        return [
            'plain string' => [
                'hello world',
                'hello world'
            ],
            'single quote' => [
                "it's a test",
                "it\\'s a test"
            ],
            'double quote' => [
                'say "hello"',
                'say \\"hello\\"'
            ],
            'backslash' => [
                'path\\to\\file',
                'path\\\\to\\\\file'
            ],
            'newline' => [
                "line1\nline2",
                'line1\\nline2'
            ],
            'carriage return' => [
                "line1\rline2",
                'line1\\rline2'
            ],
            'null byte' => [
                "null\0byte",
                'null\\0byte'
            ],
            'percent sign' => [
                '100% complete',
                '100\\% complete'
            ],
            'multiple special chars' => [
                "it's \"100%\" done\n",
                "it\\'s \\\"100\\%\\\" done\\n"
            ],
            'empty string' => [
                '',
                ''
            ],
            'SQL injection attempt' => [
                "'; DROP TABLE users; --",
                "\\'; DROP TABLE users; --"
            ],
        ];
    }

    public function testEscapeIsStatic(): void
    {
        // Verify escape can be called statically without instance
        $result = Jkl_Db::escape('test');
        $this->assertEquals('test', $result);
    }
}
