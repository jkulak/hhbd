<?php

use PHPUnit\Framework\TestCase;

class Jkl_OgTest extends TestCase
{
    private Jkl_Og $og;

    protected function setUp(): void
    {
        $this->og = new Jkl_Og('HHBD');
    }

    public function testConstructorSetsSiteName(): void
    {
        $metadata = $this->og->getMetaData();
        $this->assertStringContainsString('og:site_name', $metadata);
        $this->assertStringContainsString('HHBD', $metadata);
    }

    public function testSetTitle(): void
    {
        $this->og->setTitle('Test Title');
        $metadata = $this->og->getMetaData();

        $this->assertStringContainsString('og:title', $metadata);
        $this->assertStringContainsString('Test Title', $metadata);
    }

    public function testSetTitleEscapesQuotes(): void
    {
        $this->og->setTitle('Test "with" quotes');
        $metadata = $this->og->getMetaData();

        $this->assertStringContainsString('&quot;', $metadata);
        $this->assertStringNotContainsString('content="Test "with"', $metadata);
    }

    public function testSetDescription(): void
    {
        $this->og->setDescription('This is a test description');
        $metadata = $this->og->getMetaData();

        $this->assertStringContainsString('og:description', $metadata);
        $this->assertStringContainsString('This is a test description', $metadata);
    }

    public function testSetDescriptionTruncatesLongText(): void
    {
        $longDescription = str_repeat('word ', 100);
        $this->og->setDescription($longDescription);
        $metadata = $this->og->getMetaData();

        // Description should be trimmed (uses Jkl_Tools_String::trim_str with 297)
        $this->assertStringContainsString('og:description', $metadata);
        $this->assertStringContainsString('...', $metadata);
    }

    public function testSetImage(): void
    {
        $this->og->setImage('https://example.com/image.jpg');
        $metadata = $this->og->getMetaData();

        $this->assertStringContainsString('og:image', $metadata);
        $this->assertStringContainsString('https://example.com/image.jpg', $metadata);
    }

    public function testSetType(): void
    {
        $this->og->setType('article');
        $metadata = $this->og->getMetaData();

        $this->assertStringContainsString('og:type', $metadata);
        $this->assertStringContainsString('article', $metadata);
    }

    public function testIncludesFacebookAppId(): void
    {
        $metadata = $this->og->getMetaData();

        $this->assertStringContainsString('fb:app_id', $metadata);
        $this->assertStringContainsString(Jkl_Og::HHBD_FACEBOOK_APP_ID, $metadata);
    }

    public function testMetadataIsValidHtml(): void
    {
        $this->og->setTitle('Test');
        $this->og->setDescription('Description');
        $this->og->setImage('https://example.com/img.jpg');
        $this->og->setType('website');

        $metadata = $this->og->getMetaData();

        // Each meta tag should be properly closed
        $metaTags = explode("\n", trim($metadata));
        foreach ($metaTags as $tag) {
            if (!empty($tag)) {
                $this->assertStringStartsWith('<meta', $tag);
                $this->assertStringEndsWith('/>', $tag);
            }
        }
    }

    public function testOnlySetFieldsAreIncluded(): void
    {
        // Only set title, don't set description/image/type
        $this->og->setTitle('Only Title');
        $metadata = $this->og->getMetaData();

        $this->assertStringContainsString('og:title', $metadata);
        $this->assertStringNotContainsString('og:description', $metadata);
        $this->assertStringNotContainsString('og:image', $metadata);
        $this->assertStringNotContainsString('og:type', $metadata);
        // site_name and fb:app_id are always included
        $this->assertStringContainsString('og:site_name', $metadata);
        $this->assertStringContainsString('fb:app_id', $metadata);
    }
}
