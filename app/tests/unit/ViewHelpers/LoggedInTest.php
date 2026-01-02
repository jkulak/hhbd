<?php

use PHPUnit\Framework\TestCase;
use Mockery as m;

class Zend_View_Helper_LoggedInTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        // Reset the Zend_Auth singleton
        $this->resetZendAuth();
    }

    private function resetZendAuth(): void
    {
        $reflection = new ReflectionClass('Zend_Auth');
        $instance = $reflection->getProperty('_instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    public function testLoggedInReturnsFalseWhenNotAuthenticated(): void
    {
        // Reset to ensure clean state
        $this->resetZendAuth();

        // Use non-persistent storage to avoid session issues in tests
        $auth = Zend_Auth::getInstance();
        $auth->setStorage(new Zend_Auth_Storage_NonPersistent());

        $helper = new Zend_View_Helper_LoggedIn();
        $result = $helper->LoggedIn();

        $this->assertFalse($result);
    }

    public function testLoggedInReturnsIdentityWhenAuthenticated(): void
    {
        // Create a mock identity
        $identity = (object) [
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com'
        ];

        // Get auth instance and set up storage
        $auth = Zend_Auth::getInstance();
        $storage = new Zend_Auth_Storage_NonPersistent();
        $storage->write($identity);
        $auth->setStorage($storage);

        $helper = new Zend_View_Helper_LoggedIn();
        $result = $helper->LoggedIn();

        $this->assertNotFalse($result);
        $this->assertEquals('testuser', $result->username);
        $this->assertEquals('test@example.com', $result->email);
    }

    public function testLoggedInReturnsArrayIdentity(): void
    {
        $identity = [
            'id' => 42,
            'name' => 'Test User'
        ];

        $auth = Zend_Auth::getInstance();
        $storage = new Zend_Auth_Storage_NonPersistent();
        $storage->write($identity);
        $auth->setStorage($storage);

        $helper = new Zend_View_Helper_LoggedIn();
        $result = $helper->LoggedIn();

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['id']);
        $this->assertEquals('Test User', $result['name']);
    }
}
