<?php

namespace Dpws\RemoteAssets;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;

/**
 * Reads REMOTE_ASSETS (e.g. "https://user:pass@domain.com") and builds remote asset URLs.
 * Credentials never reach the browser: with credentials, URLs point at the local proxy route.
 */
class RemoteAssets
{
    use Configurable;

    public const ENV_VAR = 'REMOTE_ASSETS';

    /**
     * Local URL segment that proxies files from the remote when credentials are set.
     */
    private static string $proxy_route = '_remote-assets';

    /**
     * Never enabled outside dev, even if the classes are wired up manually.
     */
    public static function isEnabled(): bool
    {
        return Director::isDev() && static::getParts() !== null;
    }

    /**
     * Remote URLs are used on the frontend only — not in CLI tasks or the CMS.
     */
    public static function isActiveForRequest(): bool
    {
        if (!static::isEnabled() || Director::is_cli()) {
            return false;
        }

        if (class_exists(LeftAndMain::class) && Controller::curr() instanceof LeftAndMain) {
            return false;
        }

        return true;
    }

    public static function getProxyRoute(): string
    {
        return trim(static::config()->get('proxy_route'), '/');
    }

    public static function getBaseURL(): ?string
    {
        $parts = static::getParts();
        if (!$parts) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = rtrim($parts['path'] ?? '', '/');

        return $parts['scheme'] . '://' . $parts['host'] . $port . $path;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function getCredentials(): ?array
    {
        $parts = static::getParts();
        if (empty($parts['user'])) {
            return null;
        }

        return [rawurldecode($parts['user']), rawurldecode($parts['pass'] ?? '')];
    }

    /**
     * URL the browser should load for an asset file ID (path relative to assets/).
     */
    public static function getURLForFileID(string $fileID): string
    {
        if (static::getCredentials()) {
            return Controller::join_links(Director::baseURL(), static::getProxyRoute(), static::encodePath($fileID));
        }

        return static::getRemoteURLForFileID($fileID);
    }

    /**
     * Direct remote URL (no credentials) — used by the proxy to fetch the file.
     */
    public static function getRemoteURLForFileID(string $fileID): string
    {
        return static::getBaseURL() . '/' . ASSETS_DIR . '/' . static::encodePath($fileID);
    }

    protected static function encodePath(string $fileID): string
    {
        $segments = explode('/', str_replace('\\', '/', ltrim($fileID, '/')));

        return implode('/', array_map('rawurlencode', $segments));
    }

    protected static function getParts(): ?array
    {
        $url = Environment::getEnv(static::ENV_VAR);
        if (!$url) {
            return null;
        }

        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $parts;
    }
}
