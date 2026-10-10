<?php

use PHPUnit\Framework\TestCase;

/**
 * Google's tags as the head carries them (#161, #162): consent first, everything denied; GA4
 * with what the page is; AdSense's script; nothing of GA4 without a measurement id.
 */
class Zend_View_Helper_AnalyticsTest extends TestCase
{
    private const ID = 'G-200N6YNR76';

    public function testConsentIsDeniedBeforeAnyTagIsConfigured(): void
    {
        $script = Zend_View_Helper_Analytics::script(self::ID, array('content_group' => 'album'), array(), null);

        $consent = strpos($script, "gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied'");
        $config = strpos($script, "gtag('config', \"G-200N6YNR76\"");
        $this->assertNotFalse($consent);
        $this->assertNotFalse($config);
        $this->assertLessThan($config, $consent);
        $this->assertStringContainsString('<script async src="https://www.googletagmanager.com/gtag/js?id=G-200N6YNR76"></script>', $script);
    }

    public function testThePageViewSaysWhatThePageIsAndWhichEntity(): void
    {
        $script = Zend_View_Helper_Analytics::script(self::ID, array(
            'content_group' => 'album', 'entity_id' => 535, 'entity_name' => 'Wdowa - Superextra',
            'user_properties' => array('logged_in' => 'no'),
        ), array(), null);

        $this->assertStringContainsString('{"content_group":"album","entity_id":535,"entity_name":"Wdowa - Superextra","user_properties":{"logged_in":"no"}}', $script);
    }

    public function testANameCannotCloseTheScript(): void
    {
        $script = Zend_View_Helper_Analytics::script(self::ID, array('entity_name' => '</script><script>alert(1)</script>'), array(), null);

        $this->assertSame(2, substr_count($script, '</script>'), 'only the two scripts close');
        $this->assertStringContainsString('</script>', $script);
    }

    public function testEventsFollowThePageView(): void
    {
        $script = Zend_View_Helper_Analytics::script(self::ID, array(), array(array('login', array('method' => 'email')), array('sign_up', array())), null);

        $this->assertStringContainsString("gtag('event', \"login\", {\"method\":\"email\"});", $script);
        $this->assertStringContainsString("gtag('event', \"sign_up\", {});", $script);
        $this->assertLessThan(strpos($script, "gtag('event'"), strpos($script, "gtag('config'"));
    }

    public function testWithoutAMeasurementIdOnlyTheConsentDefaultsAndAdsLoad(): void
    {
        $script = Zend_View_Helper_Analytics::script(null, array('content_group' => 'home'), array(array('login', array())), 'ca-pub-6149271850793027');

        $this->assertStringContainsString("gtag('consent', 'default'", $script);
        $this->assertStringNotContainsString("gtag('config'", $script);
        $this->assertStringNotContainsString('googletagmanager.com', $script);
        $this->assertStringNotContainsString("gtag('event'", $script);
        $this->assertStringContainsString('<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6149271850793027" crossorigin="anonymous"></script>', $script);
        $this->assertStringContainsString('<meta name="google-adsense-account" content="ca-pub-6149271850793027">', $script);
    }

    /**
     * @dataProvider measurementIds
     */
    public function testOnlyAWellFormedMeasurementIdTurnsAnalyticsOn(string $value, ?string $expected): void
    {
        $this->assertSame($expected, Zend_View_Helper_Analytics::measurementIdOf($value));
    }

    public static function measurementIds(): array
    {
        return array(
            'production'        => array('G-200N6YNR76', 'G-200N6YNR76'),
            'with spaces'       => array(' G-200N6YNR76 ', 'G-200N6YNR76'),
            'none'              => array('', null),
            'a Universal one'   => array('UA-3311418-1', null),
            'markup'            => array('G-1"><script>', null),
        );
    }

    /**
     * @dataProvider pages
     */
    public function testAPageWithoutItsOwnWordGetsAGroupFromItsControllerAndAction(string $controller, string $action, string $group): void
    {
        $this->assertSame($group, Zend_View_Helper_Analytics::groupOf($controller, $action));
    }

    public static function pages(): array
    {
        return array(
            'home'           => array('index', 'index', 'home'),
            'about'          => array('index', 'about', 'about'),
            'privacy'        => array('index', 'privacy', 'privacy'),
            'an album'       => array('album', 'view', 'album'),
            'the album list' => array('album', 'index', 'album list'),
            'premieres'      => array('album', 'announced', 'album list'),
            'top 10'         => array('top', 'index', 'top10'),
            'login'          => array('user', 'login', 'account'),
            'a profile'      => array('user', 'view', 'user'),
            'search'         => array('search', 'index', 'search'),
        );
    }
}
