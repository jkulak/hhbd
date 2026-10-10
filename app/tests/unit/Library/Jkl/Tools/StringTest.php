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
        // #163: characters, a cut after a whole word, "..." only when something was cut
        return [
            'a short text keeps its end, without dots' => [
                'hello',
                10,
                true,
                'hello'
            ],
            'short string without dots' => [
                'hello',
                10,
                false,
                'hello'
            ],
            'a long text is cut after the last word that fits, dots included in the length' => [
                'this is a very long string that needs truncation',
                10,
                true,
                'this is...'
            ],
            'without dots the whole length is for words' => [
                'this is a very long string that needs truncation',
                10,
                false,
                'this is a'
            ],
            'a word ending right at the limit is kept' => [
                'exactly ten',
                10,
                true,
                'exactly...'
            ],
            'a text shorter than the limit gets no dots' => [
                'short',
                20,
                true,
                'short'
            ],
            'string equal to limit' => [
                'exactly ten',
                11,
                false,
                'exactly ten'
            ],
            'a Polish word is not cut short by a letter' => [
                'Za wyprodukowanie utworu odpowiada PLN.Beatz',
                30,
                true,
                'Za wyprodukowanie utworu...'
            ],
            'nor a Polish letter in two' => [
                'Mamy dla Was przedsmak tego co wykluwa się w Gdyni',
                41,
                true,
                'Mamy dla Was przedsmak tego co wykluwa...'
            ],
            'punctuation before the cut goes, the dots stand for it' => [
                'Fani nie mogą się doczekać! Album wyjdzie wiosną.',
                30,
                true,
                'Fani nie mogą się doczekać...'
            ],
        ];
    }

    public function testAMetaDescriptionKeepsNoTagsAndNoEntities(): void
    {
        $this->assertSame(
            'Wydał 5 świetnie przyjętych płyt - z zespołem Flexxip ("Fach..." T1/Teraz/Pomaton 2003 r.)',
            Jkl_Tools_String::metaDescription('<p>Wydał 5 świetnie przyjętych płyt - z zespołem <a href="/x">Flexxip</a> (&quot;Fach...&quot; T1/Teraz/Pomaton 2003 r.)</p>')
        );
    }

    public function testAMetaDescriptionPutsASpaceWhereALineOrAParagraphEnded(): void
    {
        $this->assertSame(
            'Jestem natchniona, nie zrozumiesz tego... Za oknem jakieś... Ale świecą gwiazdy',
            Jkl_Tools_String::metaDescription("Jestem natchniona, nie zrozumiesz tego...<br />\r\nZa oknem jakieś...<br>Ale świecą&nbsp;gwiazdy</p><p>")
        );
    }

    public function testAMetaDescriptionLeavesAShortTextAsItIs(): void
    {
        $this->assertSame('Najświeższe aktualności', Jkl_Tools_String::metaDescription('  Najświeższe   aktualności '));
    }

    public function testALongMetaDescriptionIsCutAfterAWordWithinItsLength(): void
    {
        $text = str_repeat('źdźbło ', 40);
        $description = Jkl_Tools_String::metaDescription($text);

        $this->assertLessThanOrEqual(160, mb_strlen($description, 'UTF-8'));
        $this->assertStringEndsWith('źdźbło...', $description);
        $this->assertSame(1, preg_match('//u', $description), 'still valid UTF-8');
    }

    public function testALongMetaDescriptionWithoutSpacesIsCutInCharactersNotBytes(): void
    {
        $description = Jkl_Tools_String::metaDescription(str_repeat('ż', 200), 20);

        $this->assertSame(str_repeat('ż', 17) . '...', $description);
    }

    public function testANewsExcerptIsPlainTextWithASpaceWhereALineEnded(): void
    {
        $this->assertSame(
            'Bit do utworu wyprodukował PLN.Beatz. źródło: facebook.com/RadomKeKe',
            Jkl_Tools_String::excerpt('<p>Bit do utworu wyprodukował PLN.Beatz.<br />źródło: facebook.com/RadomKeKe</p>', 200)
        );
    }

    public function testAnExcerptEndsWithAWholeWordAndNeverSplitsALetter(): void
    {
        $excerpt = Jkl_Tools_String::excerpt(str_repeat('Leh nie osiadł na laurach tylko zbiera świeży materiał. ', 10), 200);

        $this->assertLessThanOrEqual(200, mb_strlen($excerpt, 'UTF-8'));
        $this->assertSame(1, preg_match('//u', $excerpt), 'valid UTF-8');
        $this->assertMatchesRegularExpression('/ (Leh|nie|osiadł|na|laurach|tylko|zbiera|świeży|materiał)\.\.\.$/u', $excerpt);
    }

    public function testAnExcerptOfAShortTextHasNoDots(): void
    {
        $this->assertSame('Fani nie mogą się doczekać!', Jkl_Tools_String::excerpt('Fani nie mogą się doczekać!', 200));
    }
}
