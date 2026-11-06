# Remote Assets — developer notes

## Classes

| Class | Role |
|---|---|
| `Dpws\RemoteAssets\RemoteAssets` | Reads `REMOTE_ASSETS`, decides if remote URLs apply to the current request, builds URLs |
| `Dpws\RemoteAssets\RemoteAssetStore` | Replaces `FlysystemAssetStore` via Injector; overrides `exists()`, `getAsURL()`, `getMimeType()`, `getMetadata()` |
| `Dpws\RemoteAssets\RemoteAssetsMiddleware` | Answers `/{proxy_route}/{path}` by fetching the file from the remote with basic auth |

## Why overriding `exists()` is enough for variants

`ImageManipulation::manipulate()` only generates a variant when the store says it doesn't exist:

```php
if (!$store->exists($filename, $hash, $variant)) {
    // generate — needs the local original
} else {
    // only build the variant tuple, no file access
}
```

With `exists()` returning `true` for missing files, the variant name is built deterministically
(`NaturalFileIDHelper`) and matches the file the remote site generated.

## When remote URLs are used

`RemoteAssets::isActiveForRequest()` returns `true` only when:

- `REMOTE_ASSETS` is a valid URL with scheme and host
- the request is not CLI
- the current controller is not a `LeftAndMain` (CMS)

The config block itself (`_config/config.yml`) only loads with `envvarset: REMOTE_ASSETS` and `environment: dev`.

## Request flow with credentials

```
<img src="/_remote-assets/Uploads/image__FillWzQwMCwzMDBd.jpg">
  → local site, file not on disk → index.php
  → RemoteAssetsMiddleware
  → GET https://remote/assets/Uploads/image__FillWzQwMCwzMDBd.jpg (basic auth)
  → bytes streamed back, nothing stored
```

## Running tests

Tests aren't shipped with the package (`.gitattributes`), so run them in a clone of the repository.
No database is needed.

```sh
git clone https://github.com/dpezer/silverstripe-remote-assets.git
cd silverstripe-remote-assets
composer install
vendor/bin/phpunit   # unit tests
vendor/bin/phpcs     # PSR-12 code style
```
