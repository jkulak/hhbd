<?php

use PHPUnit\Framework\TestCase;

/**
 * A stylesheet's or a script's address changes with the file (#149), so a visitor never gets
 * one kept from before a release.
 */
class Zend_View_Helper_AssetTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/hhbd-asset-' . uniqid();
        mkdir($this->root . '/css', 0777, true);
        file_put_contents($this->root . '/css/s.css', 'body { color: black; }');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/css/s.css');
        @rmdir($this->root . '/css');
        @rmdir($this->root);
    }

    public function testAFilesAddressCarriesTheStartOfItsHash(): void
    {
        $this->assertSame(
            '/css/s.css?v=' . substr(md5('body { color: black; }'), 0, 8),
            Zend_View_Helper_Asset::address('/css/s.css', $this->root)
        );
    }

    public function testTheAddressChangesWhenTheFileDoes(): void
    {
        $before = Zend_View_Helper_Asset::address('/css/s.css', $this->root);
        file_put_contents($this->root . '/css/s.css', 'body { color: white; }');

        $this->assertNotSame($before, Zend_View_Helper_Asset::address('/css/s.css', $this->root));
    }

    public function testAFileThatIsNotThereKeepsItsAddress(): void
    {
        $this->assertSame('/css/none.css', Zend_View_Helper_Asset::address('/css/none.css', $this->root));
    }
}
