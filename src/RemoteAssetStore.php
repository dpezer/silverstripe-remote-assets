<?php

namespace Dpws\RemoteAssets;

use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use SilverStripe\Assets\FilenameParsing\ParsedFileID;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;

/**
 * Treats files missing locally as existing and points their URLs at the remote server.
 * Variants (Fill, ScaleWidth, Format('webp'), ...) are not generated — only their names are built,
 * so they resolve to the same variant files production already rendered.
 */
class RemoteAssetStore extends FlysystemAssetStore
{
    public function exists($filename, $hash, $variant = null)
    {
        if (parent::exists($filename, $hash, $variant)) {
            return true;
        }

        return !empty($filename) && !empty($hash) && RemoteAssets::isActiveForRequest();
    }

    public function getAsURL($filename, $hash, $variant = null, $grant = true)
    {
        if (!RemoteAssets::isActiveForRequest() || parent::exists($filename, $hash, $variant)) {
            return parent::getAsURL($filename, $hash, $variant, $grant);
        }

        $fileID = $this->getPublicResolutionStrategy()->buildFileID(new ParsedFileID($filename, $hash, $variant));

        return RemoteAssets::getURLForFileID($fileID);
    }

    public function getMimeType($filename, $hash, $variant = null)
    {
        $mimeType = parent::getMimeType($filename, $hash, $variant);
        if ($mimeType || !RemoteAssets::isActiveForRequest()) {
            return $mimeType;
        }

        $fileID = $this->getPublicResolutionStrategy()->buildFileID(new ParsedFileID($filename, $hash, $variant));

        return (new ExtensionMimeTypeDetector())->detectMimeTypeFromPath($fileID) ?? 'application/octet-stream';
    }

    public function getMetadata($filename, $hash, $variant = null)
    {
        return parent::getMetadata($filename, $hash, $variant) ?? [];
    }
}
