<?php

use PHPUnit\Framework\TestCase;

/**
 * A user's password (#41): password_hash() for new ones, the MD5 from before still accepted
 * with the old salt, and a login with it writing the new hash.
 */
class Model_User_PasswordTest extends TestCase
{
    private const SALT = 'test-legacy-salt';

    private $adapter;
    private $saltBefore;

    protected function setUp(): void
    {
        $this->saltBefore = getenv('LEGACY_PASSWORD_SALT');
        putenv('LEGACY_PASSWORD_SALT=' . self::SALT);
        $this->adapter = $this->createMock(Zend_Db_Adapter_Abstract::class);
    }

    protected function tearDown(): void
    {
        putenv(false === $this->saltBefore ? 'LEGACY_PASSWORD_SALT' : 'LEGACY_PASSWORD_SALT=' . $this->saltBefore);
    }

    /** The account row as hhb_users has it, with $stored for its password */
    private function account($stored)
    {
        return array('usr_id' => '7', 'usr_password' => $stored, 'usr_display_name' => 'Tester', 'usr_is_admin' => 'no', 'usr_login_count' => '3');
    }

    private function users()
    {
        return new Model_User(array('db' => $this->adapter));
    }

    public function testANewPasswordIsHashedWithAWorkFactorNotMd5(): void
    {
        $hash = Model_User_Password::hash('sekret123');

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertFalse(Model_User_Password::isLegacy($hash));
        $this->assertTrue(Model_User_Password::verify('sekret123', $hash, ''));
    }

    public function testAnMd5FromBeforeIsAcceptedWithTheOldSaltOnly(): void
    {
        $md5 = md5('sekret123' . self::SALT);

        $this->assertTrue(Model_User_Password::isLegacy($md5));
        $this->assertTrue(Model_User_Password::verify('sekret123', $md5, self::SALT));
        $this->assertFalse(Model_User_Password::verify('sekret123', $md5, ''), 'no salt set, no MD5 login');
        $this->assertFalse(Model_User_Password::verify('sekret123', $md5, 'another salt'));
    }

    public function testAnMd5NeedsANewHashAndACurrentHashDoesNot(): void
    {
        $this->assertTrue(Model_User_Password::needsRehash(md5('x' . self::SALT)));
        $this->assertFalse(Model_User_Password::needsRehash(Model_User_Password::hash('x')));
    }

    public function testLoggingInWithAnMd5WritesAPasswordHashInItsPlace(): void
    {
        $this->adapter->method('fetchRow')->willReturn($this->account(md5('sekret123' . self::SALT)));
        $this->adapter->expects($this->once())->method('update')->with(
            'hhb_users',
            $this->callback(function ($set) {
                return isset($set['usr_password'])
                    && 0 === strpos($set['usr_password'], '$2y$')
                    && password_verify('sekret123', $set['usr_password'])
                    && 4 === $set['usr_login_count'];
            }),
            array('usr_id = ?' => 7)
        )->willReturn(1);

        $account = $this->users()->authenticate('tester@example.com', 'sekret123');

        $this->assertSame('Tester', $account->usr_display_name);
        $this->assertObjectNotHasProperty('usr_password', $account, 'the session never keeps the hash');
    }

    public function testLoggingInWithAPasswordHashCountsTheLoginAndKeepsTheHash(): void
    {
        $this->adapter->method('fetchRow')->willReturn($this->account(Model_User_Password::hash('sekret123')));
        $this->adapter->expects($this->once())->method('update')->with(
            'hhb_users',
            $this->callback(function ($set) {
                return !isset($set['usr_password']) && 4 === $set['usr_login_count'] && isset($set['usr_last_login']);
            }),
            array('usr_id = ?' => 7)
        )->willReturn(1);

        $this->assertSame('7', $this->users()->authenticate('tester@example.com', 'sekret123')->usr_id);
    }

    /**
     * @dataProvider storedPasswords
     */
    public function testAWrongPasswordLogsNobodyInAndWritesNothing(string $stored): void
    {
        $this->adapter->method('fetchRow')->willReturn($this->account($stored));
        $this->adapter->expects($this->never())->method('update');

        $this->assertNull($this->users()->authenticate('tester@example.com', 'wrong-password'));
    }

    public static function storedPasswords(): array
    {
        return array(
            'an MD5 from before' => array(md5('sekret123' . self::SALT)),
            'a password hash'    => array(password_hash('sekret123', PASSWORD_DEFAULT)),
        );
    }

    public function testAnEmailWithNoAccountLogsNobodyIn(): void
    {
        $this->adapter->method('fetchRow')->willReturn(false);
        $this->adapter->expects($this->never())->method('update');

        $this->assertNull($this->users()->authenticate('nobody@example.com', 'sekret123'));
    }
}
