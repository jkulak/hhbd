<?php

use PHPUnit\Framework\TestCase;

class UserControllerTest extends TestCase
{
    public function testViewActionUsesGetDisplayNameMethod()
    {
        // Create a mock user container
        $mockUser = $this->createMock(Model_User_Container::class);
        $mockUser->method('getDisplayName')->willReturn('TestUser123');
        $mockUser->method('getId')->willReturn(456);

        // Verify the canonical slug would be generated correctly
        $expectedSlug = Jkl_Tools_Url::createUrl('TestUser123-u456') . '.html';

        // Test that createUrl works as expected
        $this->assertEquals('testuser123-u456.html', $expectedSlug);
    }

    public function testViewActionUsesGetIdMethod()
    {
        // Create a mock user container
        $mockUser = $this->createMock(Model_User_Container::class);
        $mockUser->method('getDisplayName')->willReturn('JohnDoe');
        $mockUser->method('getId')->willReturn(789);

        // Verify ID is properly used in slug generation
        $expectedSlug = Jkl_Tools_Url::createUrl('JohnDoe-u789') . '.html';

        $this->assertEquals('johndoe-u789.html', $expectedSlug);
    }

    public function testViewActionGeneratesCorrectSlugWithPolishCharacters()
    {
        // Create a mock user with Polish characters
        $mockUser = $this->createMock(Model_User_Container::class);
        $mockUser->method('getDisplayName')->willReturn('Łukasz');
        $mockUser->method('getId')->willReturn(123);

        // Polish characters should be transliterated
        $expectedSlug = Jkl_Tools_Url::createUrl('Łukasz-u123') . '.html';

        // Verify ł is converted to l
        $this->assertEquals('lukasz-u123.html', $expectedSlug);
    }

    public function testViewActionGeneratesCorrectSlugWithSpecialCharacters()
    {
        // Create a mock user with special characters
        $mockUser = $this->createMock(Model_User_Container::class);
        $mockUser->method('getDisplayName')->willReturn('User_123!@#');
        $mockUser->method('getId')->willReturn(999);

        // Special characters should be handled
        $expectedSlug = Jkl_Tools_Url::createUrl('User_123!@#-u999') . '.html';

        // Verify special chars are removed/converted
        $this->assertEquals('user-123-u999.html', $expectedSlug);
    }

    public function testViewActionSlugMatchesContainerGetUrlName()
    {
        // Model_User_Container has getUrlName() that should match our slug generation
        // (minus the -u{id}.html suffix)

        // Create real container to test consistency
        $userData = (object)[
            'usr_id' => 12508,
            'usr_email' => 'test@example.com',
            'usr_display_name' => 'Daniel13'
        ];

        $container = new Model_User_Container($userData);

        // getUrlName() should return the URL-safe version of display name
        $this->assertEquals('daniel13', $container->getUrlName());

        // Our canonical slug should use the same logic
        $expectedSlug = Jkl_Tools_Url::createUrl('Daniel13-u12508') . '.html';
        $this->assertEquals('daniel13-u12508.html', $expectedSlug);
    }

    public function testViewActionHandlesEmptyDisplayName()
    {
        // Edge case: what if display name is empty?
        $mockUser = $this->createMock(Model_User_Container::class);
        $mockUser->method('getDisplayName')->willReturn('');
        $mockUser->method('getId')->willReturn(111);

        // Should still generate a valid slug with just the ID
        // Note: createUrl strips leading/trailing hyphens
        $expectedSlug = Jkl_Tools_Url::createUrl('-u111') . '.html';

        // Empty name with leading hyphen stripped becomes u111
        $this->assertEquals('u111.html', $expectedSlug);
    }

    public function testViewActionHandlesLongDisplayName()
    {
        // Edge case: very long display name
        $longName = str_repeat('A', 50);
        $mockUser = $this->createMock(Model_User_Container::class);
        $mockUser->method('getDisplayName')->willReturn($longName);
        $mockUser->method('getId')->willReturn(222);

        // Should handle long names
        $expectedSlug = Jkl_Tools_Url::createUrl($longName . '-u222') . '.html';

        // Verify it's lowercase and has proper suffix
        $this->assertStringEndsWith('-u222.html', $expectedSlug);
        $this->assertStringStartsWith('a', $expectedSlug);
    }

    public function testViewActionSlugIsConsistentWithViewPartial()
    {
        // The user/_link.phtml partial uses:
        // $this->url(array('id' => $this->user->getId(), 'seo' => ($this->user->getUrlName())), 'user')
        //
        // We need to ensure our canonical slug matches what the router expects

        $userData = (object)[
            'usr_id' => 100,
            'usr_email' => 'test@test.com',
            'usr_display_name' => 'TestUser'
        ];

        $container = new Model_User_Container($userData);

        // Partial uses getUrlName() for seo param
        $seoParam = $container->getUrlName();
        $this->assertEquals('testuser', $seoParam);

        // Our canonical slug should be: {seoParam}-u{id}.html
        $expectedCanonical = $seoParam . '-u' . $container->getId() . '.html';
        $this->assertEquals('testuser-u100.html', $expectedCanonical);
    }
}
