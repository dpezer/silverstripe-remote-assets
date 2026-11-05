# Silverstripe Remote Assets

Working locally with a copy of the production database but without the asset files?
This module makes the frontend show images and files from a remote site (production or staging)
**without downloading or storing them locally**.

> [!WARNING]
> **Development only.** Install it with `composer require --dev` so it is never installed on
> staging or production (`composer install --no-dev`).
>
> If it does end up on a server anyway, it stays inactive: it only runs when
> `SS_ENVIRONMENT_TYPE` is `dev`. On `test` or `live` the asset store and middleware are
> not replaced, even if `REMOTE_ASSETS` is set.

## Requirements

- PHP ^8.3
- Silverstripe CMS ^6 (`silverstripe/framework` ^6, `silverstripe/assets` ^3)

## Installation

Install it as a **dev** dependency (never without `--dev`) and flush:

```sh
composer require --dev dpezer/silverstripe-remote-assets
vendor/bin/sake db:build --flush
```

## Usage

Set `REMOTE_ASSETS` in your `.env` to the remote site's base URL:

```sh
# Public remote: <img> tags point straight at the remote
REMOTE_ASSETS="https://www.example.com"

# Remote behind basic auth: files are proxied through /_remote-assets/
REMOTE_ASSETS="https://user:pass@staging.example.com"
```

Then flush (`?flush=1`). Flush again whenever you add or remove the variable.

The module is only active when **both** apply:

- `REMOTE_ASSETS` is set
- `SS_ENVIRONMENT_TYPE` is `dev`

This is checked twice: the `_config/config.yml` block only loads under these conditions, and
`RemoteAssets::isEnabled()` checks `Director::isDev()` again at runtime.

## How it works

- Files that exist locally are served locally, as usual.
- For files missing locally, the asset store reports them as existing and builds the same URL
  production uses, including resized and converted variants (e.g. `image__FillWzQwMCwzMDBd.jpg`).
  Variants are **not** generated locally.
- Without credentials, the URL points straight at `https://remote/assets/...`.
- With credentials, the URL is `/_remote-assets/...` on the local site. A middleware fetches the file
  from the remote with basic auth and streams it to the browser. Credentials never reach the page HTML.

Only the frontend is affected (including CMS preview). The CMS and CLI tasks behave as if the module
weren't installed.

## Configuration

```yaml
Dpws\RemoteAssets\RemoteAssets:
  proxy_route: '_remote-assets'

Dpws\RemoteAssets\RemoteAssetsMiddleware:
  timeout: 15          # seconds to wait for the remote
  cache_max_age: 86400 # browser cache for proxied files, in seconds
```

## Limitations

- **The variant must already exist on the remote.** A size or format the remote never rendered returns 404.
- **`$Width` / `$Height` return `0`** for remote images, because dimensions are read from the file.
- **Protected (draft) files** are not supported — they aren't publicly reachable on the remote.
- Partial caches (`<% cached %>`) may hold old URLs after toggling the variable; flush to clear them.

## Troubleshooting

### `400 Bad Request` for missing files under `/assets/` (Windows / local `*.local` domains)

Not caused by this module — it's how Silverstripe's generated `assets/.htaccess` behaves on some
local Apache setups. With this module active, missing files use `/_remote-assets/` and don't hit it,
but you'll still see it while the module is inactive or for other missing files.

On every flush, Silverstripe regenerates `public/assets/.htaccess` with:

```apache
RewriteRule .* ../index.php [QSA]
```

The relative `../index.php` works for sites in a subfolder, but on some setups (Windows Apache,
dynamic or aliased document roots) Apache can't resolve it and answers `400 Bad Request`.
Sites served from a domain root (e.g. `http://mysite.local`) can use `/index.php` instead.

Fix it on your machine only, without committing anything:

```sh
# 1. Local template override: copy of the vendor template with /index.php
mkdir -p app/templates/SilverStripe/Assets/Flysystem
sed 's#\.\./index\.php#/index.php#' \
  vendor/silverstripe/assets/templates/SilverStripe/Assets/Flysystem/PublicAssetAdapter_HTAccess.ss \
  > app/templates/SilverStripe/Assets/Flysystem/PublicAssetAdapter_HTAccess.ss

# 2. Keep the new file out of git (local-only ignore, never committed)
echo "app/templates/SilverStripe/Assets/Flysystem/PublicAssetAdapter_HTAccess.ss" >> .git/info/exclude
```

3. Flush (`?flush=1`) — `public/assets/.htaccess` is regenerated with `/index.php`.
4. If `public/assets/.htaccess` is tracked in your project, hide your local change from git:

   ```sh
   git update-index --skip-worktree public/assets/.htaccess
   ```

   To undo later: `git update-index --no-skip-worktree public/assets/.htaccess`.

Notes:

- Re-create the override after upgrading `silverstripe/assets`, so you get its template changes.
- Use `--skip-worktree`, not `--assume-unchanged`: the latter is only a performance hint and git
  may reset it.

## Documentation

See [docs/en/index.md](docs/en/index.md).

## License

[BSD-3-Clause](LICENSE)
