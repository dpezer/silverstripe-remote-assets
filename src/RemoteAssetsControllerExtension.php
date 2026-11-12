<?php

namespace Dpws\RemoteAssets;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Adds RemoteAssets.inline_css to frontend pages while remote URLs are active.
 */
class RemoteAssetsControllerExtension extends Extension
{
    protected function onAfterInit(): void
    {
        $css = trim(RemoteAssets::config()->get('inline_css') ?? '');
        if ($css === '' || !RemoteAssets::isActiveForRequest()) {
            return;
        }

        Requirements::customCSS($css, 'remote-assets');
    }
}
