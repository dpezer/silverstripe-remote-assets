<?php

namespace Dpws\RemoteAssets\Tests;

use Dpws\RemoteAssets\RemoteAssets;
use Dpws\RemoteAssets\RemoteAssetsControllerExtension;
use PHPUnit\Framework\TestCase;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Requirements;

/**
 * Plain PHPUnit test so no database is needed (SapphireTest always clears a temp database on teardown).
 */
class RemoteAssetsTest extends TestCase
{
    private ?string $originalEnv = null;

    private ?string $originalEnvironment = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Boot the Silverstripe kernel (config, injector) without a database
        SapphireTest::start();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::nest();
        $this->originalEnv = Environment::getEnv(RemoteAssets::ENV_VAR) ?: null;

        // The module only runs in dev; phpunit usually boots as live
        $kernel = Injector::inst()->get(Kernel::class);
        $this->originalEnvironment = $kernel->getEnvironment();
        $kernel->setEnvironment(Kernel::DEV);
    }

    protected function tearDown(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, $this->originalEnv ?? '');
        $this->setCliOverride(null);
        Requirements::clear();
        Injector::inst()->get(Kernel::class)->setEnvironment($this->originalEnvironment);
        Config::unnest();
        parent::tearDown();
    }

    public function testDisabledWithoutEnv(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, '');

        $this->assertFalse(RemoteAssets::isEnabled());
        $this->assertNull(RemoteAssets::getBaseURL());
    }

    public function testDisabledWithInvalidURL(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'not-a-url');

        $this->assertFalse(RemoteAssets::isEnabled());
    }

    public function testDirectURLWithoutCredentials(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://example.com:8443/');

        $this->assertNull(RemoteAssets::getCredentials());
        $this->assertSame(
            'https://example.com:8443/assets/Uploads/my%20image__FillWzQwMCwzMDBd.jpg',
            RemoteAssets::getURLForFileID('Uploads/my image__FillWzQwMCwzMDBd.jpg')
        );
    }

    public function testProxyURLWithCredentials(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://user:p%40ss@example.com');

        $this->assertSame(['user', 'p@ss'], RemoteAssets::getCredentials());
        $this->assertSame(
            Director::baseURL() . '_remote-assets/Uploads/my%20image.jpg',
            RemoteAssets::getURLForFileID('Uploads/my image.jpg')
        );
        $this->assertSame(
            'https://example.com/assets/Uploads/my%20image.jpg',
            RemoteAssets::getRemoteURLForFileID('Uploads/my image.jpg')
        );
    }

    public function testProxyRouteIsConfigurable(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://user:pass@example.com');
        RemoteAssets::config()->set('proxy_route', '/custom-route/');

        $this->assertSame('custom-route', RemoteAssets::getProxyRoute());
        $this->assertSame(
            Director::baseURL() . 'custom-route/file.jpg',
            RemoteAssets::getURLForFileID('file.jpg')
        );
    }

    public function testDisabledOutsideDev(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://example.com');
        $kernel = Injector::inst()->get(Kernel::class);

        $kernel->setEnvironment(Kernel::LIVE);
        $this->assertFalse(RemoteAssets::isEnabled());

        $kernel->setEnvironment(Kernel::TEST);
        $this->assertFalse(RemoteAssets::isEnabled());
    }

    public function testInactiveInCli(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://example.com');

        $this->assertTrue(RemoteAssets::isEnabled());
        $this->assertFalse(RemoteAssets::isActiveForRequest());
    }

    public function testInlineCSSAddedByDefault(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://example.com');
        $this->setCliOverride(false);

        $this->assertSame(
            ['remote-assets' => 'img[width="0"][height="0"] { width: auto; height: auto; }'],
            $this->getInlineCSSAfterInit()
        );
    }

    public function testInlineCSSCanBeDisabled(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://example.com');
        $this->setCliOverride(false);
        RemoteAssets::config()->set('inline_css', '');

        $this->assertSame([], $this->getInlineCSSAfterInit());
    }

    public function testInlineCSSNotAddedInCli(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://example.com');

        $this->assertSame([], $this->getInlineCSSAfterInit());
    }

    private function getInlineCSSAfterInit(): array
    {
        Requirements::clear();
        Controller::add_extension(RemoteAssetsControllerExtension::class);
        Controller::create()->extend('onAfterInit');

        return Requirements::backend()->getCustomCSS();
    }

    /**
     * Simulates a web request; Environment has no public setter for this.
     */
    private function setCliOverride(?bool $isCli): void
    {
        if (!property_exists(Environment::class, 'isCliOverride')) {
            $this->markTestSkipped('Environment::$isCliOverride is not available in this framework version');
        }

        (new \ReflectionProperty(Environment::class, 'isCliOverride'))->setValue(null, $isCli);
    }
}
