<?php

use PHPUnit\Framework\TestCase;

/**
 * Who sees what an import left to settle (#103): an admin, and nobody else.
 */
class Zend_View_Helper_IsAdminTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass('Zend_Auth');
        $instance = $reflection->getProperty('_instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    private function loggedInAs($identity): Zend_View_Helper_IsAdmin
    {
        $auth = Zend_Auth::getInstance();
        $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
        if (null !== $identity) {
            $auth->getStorage()->write($identity);
        }
        return new Zend_View_Helper_IsAdmin();
    }

    public function testAVisitorIsNoAdmin(): void
    {
        $this->assertFalse($this->loggedInAs(null)->IsAdmin());
    }

    public function testALoggedInUserIsNoAdmin(): void
    {
        $this->assertFalse($this->loggedInAs((object) array('usr_id' => 1, 'usr_is_admin' => 'no'))->IsAdmin());
    }

    public function testAnAdminIsOne(): void
    {
        $this->assertTrue($this->loggedInAs((object) array('usr_id' => 10, 'usr_is_admin' => 'yes'))->IsAdmin());
    }

    public function testAnIdentityWithoutTheFlagIsNoAdmin(): void
    {
        $this->assertFalse(Zend_View_Helper_IsAdmin::identityIsAdmin((object) array('usr_id' => 10)));
        $this->assertFalse(Zend_View_Helper_IsAdmin::identityIsAdmin('admin'));
    }
}
