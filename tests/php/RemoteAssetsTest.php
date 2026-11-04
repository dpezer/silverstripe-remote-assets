<?php

namespace Dpws\RemoteAssets\Tests;

use Dpws\RemoteAssets\RemoteAssets;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;

class RemoteAssetsTest extends SapphireTest
{
    protected $usesDatabase = false;

    private ?string $originalEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = Environment::getEnv(RemoteAssets::ENV_VAR) ?: null;
    }

    protected function tearDown(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, $this->originalEnv ?? '');
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
        $originalEnvironment = $kernel->getEnvironment();

        try {
            $kernel->setEnvironment(Kernel::LIVE);
            $this->assertFalse(RemoteAssets::isEnabled());

            $kernel->setEnvironment(Kernel::TEST);
            $this->assertFalse(RemoteAssets::isEnabled());
        } finally {
            $kernel->setEnvironment($originalEnvironment);
        }
    }

    public function testInactiveInCli(): void
    {
        Environment::setEnv(RemoteAssets::ENV_VAR, 'https://example.com');

        $this->assertTrue(RemoteAssets::isEnabled());
        $this->assertFalse(RemoteAssets::isActiveForRequest());
    }
}
