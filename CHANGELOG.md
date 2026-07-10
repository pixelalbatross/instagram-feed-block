# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/).

## [Unreleased]

## [1.2.0] - 2026-07-10

- Added CSRF protection to the Instagram OAuth flow via a signed `state` token verified on the callback.
- Encrypted the stored access token and app secret at rest using libsodium; existing plaintext values are read transparently and re-encrypted on next save.
- Hardened settings sanitization to validate each known field and drop unknown keys.
- Preserved credentials and connection on plugin deactivation; option cleanup now runs only on uninstall.
- Added automatic token-refresh retries with exponential backoff and an admin notice when reconnection is required.
- Migrated to the shared module architecture (`BaseModule`, `GetAssetInfo`) and added the `OUTSTAND_INSTAGRAM_FEED_VERSION` constant.
- Scoped OAuth callback query vars to avoid collisions with other plugins.
- Upgraded the build tooling to `@wordpress/scripts` 30 and removed unused PostCSS and PHPStan dependencies.

## [1.1.2] - 2026-06-29

- Bail gracefully when the Composer autoloader is missing instead of triggering a fatal error.

## [1.1.1] - 2026-05-08

- Added automated GitHub Release packaging via reusable release workflow; installation now points to the latest release ZIP.

## [1.1.0] - 2026-03-28

- Updated settings page title and menu title to "Instagram Feed".
- Updated repository organization from `outstand-labs` to `pixelalbatross`.

## [1.0.0] - 2026-03-06

- Initial release.
