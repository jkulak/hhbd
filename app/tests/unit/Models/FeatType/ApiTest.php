<?php

use PHPUnit\Framework\TestCase;

/**
 * A stand-in for Jkl_Db: feattypes in memory, compared without regard to case as the table's
 * collation compares them, and every statement recorded with its bound values.
 */
class Model_FeatType_FakeDb
{
    public $roles = array(0 => null, 1 => 'Rap', 2 => 'Śpiew', 32 => 'Cuty');
    public $statements = array();
    private $lastInsertId = 0;

    public function fetchAll($query, array $bind = array())
    {
        $this->statements[] = array($query, $bind);
        foreach ($this->roles as $id => $name) {
            if (null !== $name && mb_strtolower($name, 'UTF-8') === mb_strtolower($bind[0], 'UTF-8')) {
                return array(array('id' => (string) $id));
            }
        }
        return array();
    }

    public function query($query, array $bind = array())
    {
        $this->statements[] = array($query, $bind);
        $this->lastInsertId = max(array_keys($this->roles)) + 1;
        $this->roles[$this->lastInsertId] = $bind[0];
    }

    public function lastInsertId()
    {
        return (string) $this->lastInsertId;
    }
}

class Model_FeatType_ApiTest extends TestCase
{
    private $db;
    private $api;

    protected function setUp(): void
    {
        $this->db = new Model_FeatType_FakeDb();
        $this->api = new Model_FeatType_Api($this->db);
    }

    public function testAKnownRoleResolvesToItsId(): void
    {
        $this->assertSame(1, $this->api->resolve('Rap'));
    }

    /**
     * @dataProvider spellingsProvider
     */
    public function testARoleIsFoundWhateverItsCaseAndSpacing(string $written, int $id): void
    {
        $this->assertSame($id, $this->api->resolve($written));
        $this->assertCount(4, $this->db->roles);
    }

    public function spellingsProvider(): array
    {
        return array(
            'lower case' => array('rap', 1),
            'upper case with a Polish letter' => array('ŚPIEW', 2),
            'surrounded by spaces' => array("  Cuty \n", 32),
        );
    }

    public function testAnUnknownRoleIsAddedOnceWithACapitalFirstLetter(): void
    {
        $id = $this->api->resolve('gitara   basowa');

        $this->assertSame(33, $id);
        $this->assertSame('Gitara basowa', $this->db->roles[33]);
        $this->assertSame(33, $this->api->resolve('Gitara basowa'));
        $this->assertCount(5, $this->db->roles);
    }

    public function testARoleStartingWithAPolishLetterIsCapitalisedToo(): void
    {
        $this->api->resolve('świst');

        $this->assertSame('Świst', $this->db->roles[33]);
    }

    /**
     * @dataProvider badNamesProvider
     */
    public function testANameThatIsNoRoleIsRefused(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->api->resolve($name);
    }

    public function badNamesProvider(): array
    {
        return array(
            'empty' => array(''),
            'only spaces' => array('   '),
            'longer than the unique key covers' => array(str_repeat('a', 65)),
        );
    }

    public function testTheNameReachesTheDatabaseAsABoundParameter(): void
    {
        $this->api->resolve("Rap' OR '1'='1");

        foreach ($this->db->statements as list($query, $bind)) {
            $this->assertStringNotContainsString('OR', $query);
        }
    }
}
