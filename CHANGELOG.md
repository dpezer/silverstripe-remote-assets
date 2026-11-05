# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-05

### Added

- Serve asset files missing locally from a remote site on the frontend, without storing them locally.
- `REMOTE_ASSETS` environment variable with the remote base URL.
- Direct remote URLs when `REMOTE_ASSETS` has no credentials.
- Proxy route `/_remote-assets/` with basic auth when `REMOTE_ASSETS` contains credentials
  (`https://user:pass@host`), so credentials never reach the page HTML.
- Image variants (`Fill`, `ScaleWidth`, `Convert('webp')`, …) resolve to the files the remote already generated.
- Configurable `proxy_route`, `timeout` and `cache_max_age`.
- Active only in `dev` environments, on the frontend (including CMS preview); inactive in the CMS and CLI.
- Troubleshooting docs for `400 Bad Request` on missing `/assets/` files on some local Apache setups.

[Unreleased]: https://github.com/dpezer/silverstripe-remote-assets/compare/1.0.0...HEAD
[1.0.0]: https://github.com/dpezer/silverstripe-remote-assets/releases/tag/1.0.0
