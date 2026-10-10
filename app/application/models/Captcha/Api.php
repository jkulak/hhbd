<?php

/**
 * The comment form's question, held here and answerable once (#41). The form used to carry
 * md5(answer . salt) to the browser and take it back, with the salt in the code: a bot could
 * make a pair of its own, and replay one seen once for ever. Now a question is a row of
 * captcha_challenges under a random token; the browser gets the token and the question, never
 * the answer, and checking an answer deletes the row, right or wrong.
 *
 * A question is made when someone starts a comment (the script asks for it), not with every
 * page, so a crawler reading pages writes nothing.
 */
class Model_Captcha_Api extends Jkl_Model_Api
{
    /** How long a question waits for its answer */
    public const LIFETIME_MINUTES = 60;

    private static $_instance;

    /** @return Model_Captcha_Api */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /** @param object|null $db a stand-in for the database, in a test */
    public function __construct($db = null)
    {
        if (null === $db) {
            parent::__construct();
        } else {
            $this->_db = $db;
        }
    }

    /**
     * A new question, and the token its answer is kept under: the sum of two numbers from 1 to
     * 10, as before. The questions nobody answered within the hour go first.
     *
     * @return array token, question
     */
    public function create()
    {
        $this->_db->query('DELETE FROM captcha_challenges WHERE created < NOW() - INTERVAL ' . self::LIFETIME_MINUTES . ' MINUTE');
        $a = random_int(1, 10);
        $b = random_int(1, 10);
        $token = bin2hex(random_bytes(16));
        $this->_db->query('INSERT INTO captcha_challenges (token, answer) VALUES (?, ?)', array($token, $a + $b));
        return array('token' => $token, 'question' => sprintf('Ile to %d + %d?', $a, $b));
    }

    /**
     * Whether $answer answers the question under $token. The question goes either way, so an
     * answer works once and a wrong one cannot be tried again; of two checks at once, only the
     * one that deletes the row can pass.
     */
    public function check($token, $answer)
    {
        $token = (string) $token;
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            return false;
        }
        // Read past Jkl_Db's cache: an answer must be the row as it is now
        $rows = $this->_db->query(
            'SELECT answer FROM captcha_challenges WHERE token = ? AND created >= NOW() - INTERVAL ' . self::LIFETIME_MINUTES . ' MINUTE',
            array($token)
        )->fetchAll();
        $deleted = $this->_db->query('DELETE FROM captcha_challenges WHERE token = ?', array($token))->rowCount();
        $answer = trim((string) $answer);
        return 1 === $deleted && !empty($rows) && ctype_digit($answer) && (int) $rows[0]['answer'] === (int) $answer;
    }
}
