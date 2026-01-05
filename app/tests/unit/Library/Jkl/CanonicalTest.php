<?php

use PHPUnit\Framework\TestCase;

class Jkl_CanonicalTest extends TestCase
{
    public function testGetMetaTagReturnsCorrectHtml()
    {
        $url = 'https://example.com/some-page.html';
        $result = Jkl_Canonical::getMetaTag($url);

        $this->assertStringContainsString('<link rel="canonical"', $result);
        $this->assertStringContainsString('href="https://example.com/some-page.html"', $result);
        $this->assertStringContainsString('/>', $result);
    }

    public function testGetMetaTagEscapesSpecialCharacters()
    {
        $url = 'https://example.com/page?param=value&other="quoted"';
        $result = Jkl_Canonical::getMetaTag($url);

        // Should escape ampersands and quotes
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringNotContainsString('&other=', $result); // Should be &amp;other=
    }

    public function testGetMetaTagHandlesPolishCharacters()
    {
        $url = 'https://example.com/użytkownik/test.html';
        $result = Jkl_Canonical::getMetaTag($url);

        $this->assertStringContainsString('użytkownik', $result);
    }

    public function testRedirectIfNeededDoesNotRedirectWhenSlugMatches()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/correct-slug-a123.html');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector - should NOT be called
        $redirector->expects($this->never())->method('gotoUrl');

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call the method with matching canonical slug
        Jkl_Canonical::redirectIfNeeded($controller, 'correct-slug-a123.html');
    }

    public function testRedirectIfNeededRedirectsWhenSlugDiffers()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request - current URL has wrong slug
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/wrong-slug-a123.html');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector - SHOULD be called with 301
        // Note: actual implementation passes just '/slug', not full URL
        $redirector->expects($this->once())
            ->method('gotoUrl')
            ->with('/correct-slug-a123.html', ['code' => 301]);

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call the method with different canonical slug
        Jkl_Canonical::redirectIfNeeded($controller, 'correct-slug-a123.html');
    }

    public function testRedirectIfNeededHandlesComplexUrls()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request with query string - should be stripped by strtok
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/wrong-slug-a123.html?page=2');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector - SHOULD be called
        $redirector->expects($this->once())
            ->method('gotoUrl')
            ->with('/correct-slug-a123.html', ['code' => 301]);

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call the method
        Jkl_Canonical::redirectIfNeeded($controller, 'correct-slug-a123.html');
    }

    public function testRedirectIfNeededHandlesPolishCharactersInBasename()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request with Polish characters in username
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/łukasz-testowy-u123.html');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector - SHOULD be called when slug differs
        $redirector->expects($this->once())
            ->method('gotoUrl')
            ->with('/lukasz-testowy-u123.html', ['code' => 301]);

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call the method with transliterated canonical slug
        Jkl_Canonical::redirectIfNeeded($controller, 'lukasz-testowy-u123.html');
    }

    public function testRedirectIfNeededDoesNotRedirectForMatchingBasename()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request - basename matches
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/correct-name-u123.html');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector - should NOT be called (basename matches)
        $redirector->expects($this->never())->method('gotoUrl');

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call with matching basename
        Jkl_Canonical::redirectIfNeeded($controller, 'correct-name-u123.html');
    }

    public function testRedirectIfNeededHandlesAlbumUrls()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request - artist name with +-+ separator
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/wrong-artist-wrong-album-a123.html');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector
        $redirector->expects($this->once())
            ->method('gotoUrl')
            ->with('/artist-name+-+album-title-a123.html', ['code' => 301]);

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call with album-style canonical slug
        Jkl_Canonical::redirectIfNeeded($controller, 'artist-name+-+album-title-a123.html');
    }

    public function testRedirectIfNeededHandlesUserUrls()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request - user URL with capital letters
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/Daniel13-u12508.html');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector - SHOULD be called to lowercase
        $redirector->expects($this->once())
            ->method('gotoUrl')
            ->with('/daniel13-u12508.html', ['code' => 301]);

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call with lowercase canonical slug
        Jkl_Canonical::redirectIfNeeded($controller, 'daniel13-u12508.html');
    }

    public function testRedirectIfNeededDoesNotRedirectForCorrectUserUrl()
    {
        // Mock controller
        $controller = $this->createMock(Zend_Controller_Action::class);

        // Mock request - already lowercase user URL
        $request = $this->createMock(Zend_Controller_Request_Http::class);
        $request->method('getRequestUri')->willReturn('/daniel13-u12508.html');

        $controller->method('getRequest')->willReturn($request);

        // Create a partial mock for redirector helper
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock redirector - should NOT be called (already correct)
        $redirector->expects($this->never())->method('gotoUrl');

        $controller->method('getHelper')->with('redirector')->willReturn($redirector);

        // Call with matching canonical slug
        Jkl_Canonical::redirectIfNeeded($controller, 'daniel13-u12508.html');
    }
}
