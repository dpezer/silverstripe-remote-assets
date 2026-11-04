<?php

namespace Dpws\RemoteAssets;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;

/**
 * Streams /{proxy_route}/{path} from the remote server using basic auth from REMOTE_ASSETS.
 * Nothing is written to the local filesystem.
 */
class RemoteAssetsMiddleware implements HTTPMiddleware
{
    use Configurable;

    /**
     * Seconds to wait for the remote server.
     */
    private static int $timeout = 15;

    /**
     * Browser cache lifetime for proxied files, in seconds.
     */
    private static int $cache_max_age = 86400;

    public function process(HTTPRequest $request, callable $delegate)
    {
        $prefix = RemoteAssets::getProxyRoute() . '/';
        $url = $request->getURL();

        if (!str_starts_with($url, $prefix) || !RemoteAssets::isEnabled()) {
            return $delegate($request);
        }

        $fileID = rawurldecode(substr($url, strlen($prefix)));
        if ($fileID === '' || str_contains($fileID, '..')) {
            return HTTPResponse::create('', 404);
        }

        try {
            $remote = (new Client(['timeout' => static::config()->get('timeout'), 'http_errors' => false]))->get(
                RemoteAssets::getRemoteURLForFileID($fileID),
                ['auth' => RemoteAssets::getCredentials()]
            );
        } catch (GuzzleException) {
            return HTTPResponse::create('', 502);
        }

        if ($remote->getStatusCode() !== 200) {
            return HTTPResponse::create('', $remote->getStatusCode());
        }

        HTTPCacheControlMiddleware::singleton()->publicCache(true)->setMaxAge(static::config()->get('cache_max_age'));

        $response = HTTPResponse::create((string) $remote->getBody(), 200);
        $response->addHeader('Content-Type', $remote->getHeaderLine('Content-Type') ?: 'application/octet-stream');

        return $response;
    }
}
