<?php

use PHPUnit\Framework\TestCase;

/**
 * A news item's image address (#133): under the configured path, its file name encoded, as old
 * names hold spaces, Polish letters and a literal %.
 */
class Model_News_ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        Zend_Registry::set('Config_App', array('paths' => array('newsImagePath' => '/content/news/')));
    }

    protected function tearDown(): void
    {
        Zend_Registry::_unsetInstance();
    }

    private function news($graph): Model_News_Container
    {
        return new Model_News_Container(array(
            'nws_id' => 1,
            'nws_title' => 'Premiera',
            'nws_content' => 'Treść',
            'nws_attachment_url' => $graph,
            'nws_added' => '2010-01-02 03:04:05',
        ), true);
    }

    public function testAnImageIsUnderTheNewsPath(): void
    {
        $this->assertSame('/content/news/test-news-001.jpg', $this->news('test-news-001.jpg')->attachment->url);
    }

    public function testAnOldNameWithAPolishLetterAndALiteralPercentIsEncoded(): void
    {
        $this->assertSame(
            '/content/news/_jedno%C5%9B%25C4_z_wyrazami_wielkie.jpg',
            $this->news('_jednoś%C4_z_wyrazami_wielkie.jpg')->attachment->url
        );
    }

    public function testANewsItemWithoutAnImageHasNone(): void
    {
        $this->assertNull($this->news('')->attachment);
    }
}
