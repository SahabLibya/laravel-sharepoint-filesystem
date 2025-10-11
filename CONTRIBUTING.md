# Contributing to Laravel SharePoint Filesystem

Thank you for considering contributing to Laravel SharePoint Filesystem! This document will help you understand how to contribute to this project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)

## Code of Conduct

This project and everyone participating in it is governed by a Code of Conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to dev@sahablibya.ly.

### Our Standards

- Be respectful and inclusive
- Welcome newcomers and encourage diverse perspectives
- Focus on what is best for the community
- Show empathy towards other community members

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples**
- **Describe the behavior you observed and what you expected**
- **Include your environment details:**
  - PHP version
  - Laravel version
  - Package version
  - Operating system

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- **Use a clear and descriptive title**
- **Provide a detailed description of the suggested enhancement**
- **Explain why this enhancement would be useful**
- **List any similar packages that have this feature**

### Pull Requests

We actively welcome your pull requests:

1. Fork the repo and create your branch from `main`
2. If you've added code that should be tested, add tests
3. If you've changed APIs, update the documentation
4. Ensure the test suite passes
5. Make sure your code follows the coding standards
6. Issue that pull request!

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git
- A Microsoft Azure account for testing (with app registration)

### Setup Steps

```bash
# Clone your fork
git clone https://github.com/your-username/laravel-sharepoint-filesystem.git
cd laravel-sharepoint-filesystem

# Install dependencies
composer install

# Copy environment file (if testing)
cp .env.example .env

# Configure your Azure credentials in .env
# GRAPH_CLIENT_ID=...
# GRAPH_CLIENT_SECRET=...
# GRAPH_TENANT_ID=...
```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
vendor/bin/pest tests/Unit/SharePointAdapterTest.php
```

## Pull Request Process

1. **Update Documentation**: Ensure the README.md and other documentation reflects any changes
2. **Update CHANGELOG.md**: Add a note under `[Unreleased]` describing your changes
3. **Follow Coding Standards**: Run code formatting before submitting
4. **Write Tests**: All new features should have tests
5. **One Feature Per PR**: Keep pull requests focused on a single feature or bug fix
6. **Clear Commit Messages**: Use descriptive commit messages

### Commit Message Format

```
feat: add support for large file uploads via resumable sessions
fix: correct path handling for nested directories
docs: update installation guide with Azure portal screenshots
test: add unit tests for token caching mechanism
refactor: simplify error handling in SharePointAdapter
```

Prefix types:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `test:` - Adding or updating tests
- `refactor:` - Code refactoring
- `style:` - Formatting, missing semicolons, etc.
- `chore:` - Maintenance tasks

## Coding Standards

### PHP Standards

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards.

```bash
# Format code automatically
vendor/bin/pint

# Check code style
vendor/bin/pint --test
```

### Key Conventions

- Use strict typing: `declare(strict_types=1);`
- Use typed properties and return types
- Follow PSR-4 autoloading
- Use meaningful variable and method names
- Add PHPDoc blocks for complex methods
- Keep methods focused and single-purpose

### Example Code Style

```php
<?php

declare(strict_types=1);

namespace SahabLibya\SharePointFilesystem;

use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;

class SharePointAdapter
{
    /**
     * Write file contents to SharePoint.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $endpoint = $this->baseUrl.$this->getBasePath($path).':/content';
            
            $response = Http::withToken($this->accessToken)
                ->timeout(300)
                ->withBody($contents, 'application/octet-stream')
                ->put($endpoint);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to write file: '.$response->body());
            }
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }
}
```

## Testing Guidelines

### Test Structure

- Unit tests for individual methods and classes
- Integration tests for complete workflows
- Use Pest or PHPUnit
- Mock external API calls when possible
- Test both success and failure scenarios

### Test Example

```php
it('can write a file to sharepoint', function () {
    Http::fake([
        'graph.microsoft.com/*' => Http::response(['id' => '123'], 200),
    ]);

    $adapter = new SharePointAdapter('fake-token', 'drive-id');
    $adapter->write('test.txt', 'content', new Config());

    expect(true)->toBeTrue();
});

it('throws exception when write fails', function () {
    Http::fake([
        'graph.microsoft.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $adapter = new SharePointAdapter('fake-token', 'drive-id');
    $adapter->write('test.txt', 'content', new Config());
})->throws(UnableToWriteFile::class);
```

## Documentation

### README Updates

When adding new features:
1. Add to the features list
2. Include usage examples
3. Update the API reference table
4. Add any new configuration options

### Code Comments

- Document complex algorithms
- Explain non-obvious business logic
- Include examples for public APIs
- Keep comments up-to-date with code changes

## Questions?

Don't hesitate to ask questions by:
- Opening a GitHub issue
- Starting a GitHub discussion
- Emailing dev@sahablibya.ly

## Recognition

Contributors will be recognized in:
- The project README
- Release notes
- GitHub contributors page

Thank you for contributing to Laravel SharePoint Filesystem! 🚀

