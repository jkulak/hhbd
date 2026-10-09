<?php

use PHPUnit\Framework\TestCase;

/**
 * Which users row an admin's account writes its edits as (#132): the linked one, or a new one
 * under the account's name.
 */
class Model_Audit_ApiTest extends TestCase
{
    /** A stand-in for Jkl_Db: canned answers by the start of the query, and every write kept */
    private function db(array $answers): object
    {
        return new class ($answers) {
            public $writes = array();
            private $answers;

            public function __construct(array $answers)
            {
                $this->answers = $answers;
            }

            public function fetchAll($sql, array $bind = array())
            {
                foreach ($this->answers as $start => $rows) {
                    if (0 === strpos($sql, $start)) {
                        return $rows;
                    }
                }
                return array();
            }

            public function query($sql, array $bind = array())
            {
                $this->writes[] = array($sql, $bind);
            }

            public function lastInsertId()
            {
                return 1101;
            }
        };
    }

    public function testALinkedAccountWritesAsItsRow(): void
    {
        $db = $this->db(array('SELECT ID FROM users' => array(array('ID' => '1'))));

        $this->assertSame(1, (new Model_Audit_Api($db))->userIdFor(67));
        $this->assertSame(array(), $db->writes);
    }

    public function testAnAccountWithoutARowGetsOneUnderItsName(): void
    {
        $db = $this->db(array('SELECT usr_display_name FROM hhb_users' => array(array('usr_display_name' => 'Administratorka Testowa'))));

        $this->assertSame(1101, (new Model_Audit_Api($db))->userIdFor(10));
        $this->assertCount(1, $db->writes);
        $this->assertStringStartsWith('INSERT INTO users', $db->writes[0][0]);
        $this->assertSame(array('Administratorka ', 10), $db->writes[0][1]);
    }

    public function testAnAccountThatIsNotThereIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Model_Audit_Api($this->db(array())))->userIdFor(999);
    }
}
