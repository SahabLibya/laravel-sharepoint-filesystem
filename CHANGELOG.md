# Changelog

All notable changes to `laravel-sharepoint-filesystem` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-06-30

### Added
- Added Laravel 13 compatibility for the Illuminate filesystem, HTTP, and support packages.
- Added Laravel 13 test matrix coverage for PHP 8.3, 8.4, and 8.5.
- Added PHP 8.5 to the supported package PHP constraint.
- Added CI handling for legacy Laravel 10 and 11 test lanes after Composer advisory blocking began rejecting those framework installs.
- Added a feature test that resolves the `sharepoint` and `onedrive` disks through `Storage::extend()` to guard the driver registration on every supported Laravel version.

### Fixed
- Fixed a fatal `Call to undefined method` error when resolving a disk on Laravel 13. Laravel 13 rebinds `Storage::extend()` callbacks' `$this` (and scope) to the `FilesystemManager` via `Illuminate\Support\RebindsCallbacksToSelf`; the driver factories now capture the service provider explicitly instead of relying on `$this`.

## [1.1.0] - 2026-05-31

### Added
- Added configurable Microsoft Graph copy monitor timeout and polling interval options.
- Added mocked Pest tests for path encoding, prefixes, copy monitoring, move safety, pagination, and metadata timestamps.
- Added GitHub Actions test matrix for current secure PHP and Laravel dependency combinations.

### Fixed
- Added the missing Guzzle HTTP client dependency required by Laravel's HTTP client on Laravel 10.
- Fixed Microsoft Graph path construction to encode path segments safely.
- Fixed prefixed disk handling so prefixes are not applied twice.
- Fixed copy and move behavior to wait for Microsoft Graph async copy completion before returning or deleting the source.
- Fixed directory listings to follow Microsoft Graph pagination links.
- Fixed MIME type metadata to return Unix timestamps consistently.

## [1.0.0] - 2025-01-11

### Added
- Initial release of Laravel SharePoint/OneDrive Filesystem Driver
- Full Flysystem v3 adapter implementation for Microsoft Graph API
- Client credentials authentication flow with automatic token management
- Smart token caching (58 minutes TTL)
- Support for SharePoint Document Libraries
- Support for OneDrive for Business
- Laravel 10.x, 11.x, and 12.x compatibility
- PHP 8.1+ support
- All standard Flysystem operations:
  - File operations: read, write, delete, copy, move
  - Directory operations: create, delete, list contents
  - Metadata operations: file size, last modified, MIME type
  - Stream operations for memory-efficient large file handling
- Automatic timeout handling for large files (5-minute timeout)
- Path prefixing support for organizing files
- Multiple drive support for different SharePoint sites
- OneDrive driver alias for backward compatibility
- Comprehensive documentation and installation guide
- Professional README with badges and examples
- Spatie Laravel Backup integration support

### Technical Details
- PSR-4 autoloading
- PSR-12 coding standards
- Proper exception handling with Flysystem exceptions
- Efficient path construction and normalization
- Recursive directory listing support
- Proper handling of Microsoft Graph API responses

[Unreleased]: https://github.com/sahablibya/laravel-sharepoint-filesystem/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/sahablibya/laravel-sharepoint-filesystem/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/sahablibya/laravel-sharepoint-filesystem/compare/v1.0.1...v1.1.0
[1.0.0]: https://github.com/sahablibya/laravel-sharepoint-filesystem/releases/tag/v1.0.0
