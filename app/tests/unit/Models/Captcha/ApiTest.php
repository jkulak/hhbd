<?php

use PHPUnit\Framework\TestCase;

/**
 * The comment form's question (#41): the server keeps the answer, and takes it once.
 */
class Model_Captcha_ApiTest extends TestCase
{
    private $db;
    private $captcha;

    protected function setUp(): void
    {
        $this->db = new Model_Captcha_ApiTest_Db();
        $this->captcha = new Model_Captcha_Api($this->db);
    }

    /** The answer to a question the API made, from its words */
    private function answerTo($question)
    {
        $this->assertSame(1, preg_match('/^Ile to (\d+) \+ (\d+)\?$/', $question, $m), $question);
        return (int) $m[1] + (int) $m[2];
    }

    public function testAQuestionGivesTheBrowserATokenAndTheQuestionButNotTheAnswer(): void
    {
        $challenge = $this->captcha->create();

        $this->assertSame(array('token', 'question'), array_keys($challenge));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $challenge['token']);
        $this->assertSame(array($challenge['token'] => $this->answerTo($challenge['question'])), $this->db->answers);
    }

    public function testTheRightAnswerPasses(): void
    {
        $challenge = $this->captcha->create();

        $this->assertTrue($this->captcha->check($challenge['token'], (string) $this->answerTo($challenge['question'])));
    }

    public function testAnAnswerReplayedDoesNotPassTwice(): void
    {
        $challenge = $this->captcha->create();
        $answer = (string) $this->answerTo($challenge['question']);

        $this->assertTrue($this->captcha->check($challenge['token'], $answer));
        $this->assertFalse($this->captcha->check($challenge['token'], $answer));
    }

    public function testAWrongAnswerUsesTheQuestionUp(): void
    {
        $challenge = $this->captcha->create();
        $answer = $this->answerTo($challenge['question']);

        $this->assertFalse($this->captcha->check($challenge['token'], (string) ($answer + 1)));
        $this->assertFalse($this->captcha->check($challenge['token'], (string) $answer), 'no second try');
    }

    public function testAMadeUpTokenOrANonNumberNeverPasses(): void
    {
        $challenge = $this->captcha->create();
        $answer = $this->answerTo($challenge['question']);

        $this->assertFalse($this->captcha->check(str_repeat('a', 32), (string) $answer));
        $this->assertFalse($this->captcha->check("' OR 1=1 -- ", (string) $answer));
        $this->assertFalse($this->captcha->check($challenge['token'], $answer . 'x'));
    }
}

/** captcha_challenges in memory: the statements Model_Captcha_Api runs, and nothing else */
class Model_Captcha_ApiTest_Db
{
    /** @var array token => answer */
    public $answers = array();

    public function query($sql, array $bind = array())
    {
        if (0 === strpos($sql, 'INSERT INTO captcha_challenges')) {
            $this->answers[$bind[0]] = (int) $bind[1];
            return new Model_Captcha_ApiTest_Statement(array(), 1);
        }
        if (0 === strpos($sql, 'SELECT answer FROM captcha_challenges WHERE token = ?')) {
            $rows = isset($this->answers[$bind[0]]) ? array(array('answer' => (string) $this->answers[$bind[0]])) : array();
            return new Model_Captcha_ApiTest_Statement($rows, count($rows));
        }
        if (0 === strpos($sql, 'DELETE FROM captcha_challenges WHERE token = ?')) {
            $found = isset($this->answers[$bind[0]]);
            unset($this->answers[$bind[0]]);
            return new Model_Captcha_ApiTest_Statement(array(), $found ? 1 : 0);
        }
        if (0 === strpos($sql, 'DELETE FROM captcha_challenges WHERE created <')) {
            return new Model_Captcha_ApiTest_Statement(array(), 0);
        }
        throw new LogicException('Not a statement the captcha runs: ' . $sql);
    }
}

class Model_Captcha_ApiTest_Statement
{
    private $rows;
    private $count;

    public function __construct(array $rows, $count)
    {
        $this->rows = $rows;
        $this->count = $count;
    }

    public function fetchAll()
    {
        return $this->rows;
    }

    public function rowCount()
    {
        return $this->count;
    }
}
