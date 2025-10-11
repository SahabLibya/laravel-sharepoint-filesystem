# Changelog

All notable changes to `laravel-sharepoint-filesystem` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/sahablibya/laravel-sharepoint-filesystem/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/sahablibya/laravel-sharepoint-filesystem/releases/tag/v1.0.0

