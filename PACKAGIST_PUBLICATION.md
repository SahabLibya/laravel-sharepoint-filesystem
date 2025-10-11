# Publishing to Packagist - Complete Guide

This guide will walk you through publishing your Laravel SharePoint Filesystem package to Packagist.

## Prerequisites

✅ Your package is ready (all code, tests, documentation complete)  
✅ You have a GitHub account  
✅ You have a Packagist account (sign up at https://packagist.org)

## Step 1: Initialize Git Repository

Navigate to your package directory and initialize Git:

```bash
cd /Users/M/Sites/packages/laravel-sharepoint-filesystem

# Initialize Git (if not already initialized)
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial release v1.0.0

- SharePoint/OneDrive filesystem driver for Laravel
- Client credentials authentication with auto token management
- Laravel 10, 11, 12 support
- Flysystem v3 implementation
- Complete documentation and tests"
```

## Step 2: Create GitHub Repository

### Option A: Using GitHub CLI

```bash
# Install GitHub CLI if you haven't
# brew install gh (macOS)

# Login to GitHub
gh auth login

# Create repository
gh repo create sahablibya/laravel-sharepoint-filesystem --public --source=. --remote=origin

# Push code
git push -u origin main
```

### Option B: Using GitHub Web Interface

1. Go to https://github.com/new
2. **Repository name:** `laravel-sharepoint-filesystem`
3. **Description:** "SharePoint/OneDrive filesystem driver for Laravel using Microsoft Graph API"
4. **Visibility:** Public
5. Click "Create repository"

Then connect your local repository:

```bash
# Add remote origin
git remote add origin https://github.com/sahablibya/laravel-sharepoint-filesystem.git

# Push code
git branch -M main
git push -u origin main
```

## Step 3: Create a Git Tag for Version 1.0.0

Packagist uses Git tags to identify package versions:

```bash
# Create annotated tag
git tag -a v1.0.0 -m "Release version 1.0.0

Initial release with:
- SharePoint/OneDrive filesystem driver
- Client credentials authentication
- Laravel 10, 11, 12 support
- Full Flysystem v3 implementation"

# Push tag to GitHub
git push origin v1.0.0

# Or push all tags
git push --tags
```

## Step 4: Submit to Packagist

### 4.1 Login to Packagist

1. Go to https://packagist.org
2. Click "Sign in" (use GitHub to sign in)
3. Authorize Packagist to access your GitHub account

### 4.2 Submit Your Package

1. Click "Submit" in the top navigation
2. Enter your repository URL:
   ```
   https://github.com/sahablibya/laravel-sharepoint-filesystem
   ```
3. Click "Check"
4. Packagist will validate your `composer.json`
5. If everything is correct, click "Submit"

### 4.3 Set Up Auto-Update (Recommended)

To automatically update Packagist when you push to GitHub:

#### Using GitHub Service Hook (Easiest)

1. On Packagist, go to your package page
2. Click "Settings" tab
3. Copy the webhook URL shown (looks like: `https://packagist.org/api/github?username=...`)
4. Go to your GitHub repository
5. Settings → Webhooks → Add webhook
6. Paste the Packagist webhook URL
7. Content type: `application/json`
8. Select "Just the push event"
9. Click "Add webhook"

#### Alternative: Using Packagist API Token

1. On Packagist: Profile → Settings → API Token
2. Generate a new token
3. On GitHub: Repository Settings → Secrets → Actions
4. Add secret: `PACKAGIST_TOKEN` with your token value
5. Create `.github/workflows/packagist.yml`:

```yaml
name: Update Packagist

on:
  push:
    tags:
      - 'v*'

jobs:
  packagist:
    runs-on: ubuntu-latest
    steps:
      - name: Update Packagist
        run: |
          curl -XPOST -H'content-type:application/json' \
            'https://packagist.org/api/update-package?username=sahablibya&apiToken=${{ secrets.PACKAGIST_TOKEN }}' \
            -d'{"repository":{"url":"https://github.com/sahablibya/laravel-sharepoint-filesystem"}}'
```

## Step 5: Verify Package Publication

1. Visit https://packagist.org/packages/sahablibya/laravel-sharepoint-filesystem
2. You should see your package with version 1.0.0
3. Check that all metadata is correct:
   - Description
   - Keywords
   - License
   - Authors

## Step 6: Test Installation

Test that users can install your package:

```bash
# In a test Laravel project
composer require sahablibya/laravel-sharepoint-filesystem

# Verify it installed correctly
composer show sahablibya/laravel-sharepoint-filesystem
```

## Releasing Future Versions

When you're ready to release a new version:

### 1. Update Code & Documentation

```bash
# Make your changes
git add .
git commit -m "Add new feature XYZ"
```

### 2. Update CHANGELOG.md

```markdown
## [1.1.0] - 2025-01-15

### Added
- New feature XYZ
- Support for ABC

### Fixed
- Bug in file listing
```

### 3. Create New Tag

```bash
# For bug fixes (1.0.0 → 1.0.1)
git tag -a v1.0.1 -m "Bug fixes"

# For new features (1.0.0 → 1.1.0)
git tag -a v1.1.0 -m "New features"

# For breaking changes (1.0.0 → 2.0.0)
git tag -a v2.0.0 -m "Major version with breaking changes"

# Push commits and tags
git push origin main
git push origin --tags
```

### 4. Create GitHub Release (Optional but Recommended)

1. Go to your GitHub repository
2. Click "Releases" → "Create a new release"
3. Select your tag (e.g., v1.1.0)
4. Title: "Version 1.1.0"
5. Description: Copy from CHANGELOG.md
6. Click "Publish release"

Packagist will automatically pick up the new version if you've set up webhooks.

## Semantic Versioning Guide

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.0.0 → 2.0.0): Breaking changes
- **MINOR** (1.0.0 → 1.1.0): New features, backward compatible
- **PATCH** (1.0.0 → 1.0.1): Bug fixes, backward compatible

## Troubleshooting

### Package Not Showing Up

- Wait 5-10 minutes after submission
- Check that your `composer.json` is valid
- Ensure your GitHub repository is public
- Verify the tag was pushed: `git ls-remote --tags origin`

### Auto-Update Not Working

- Check webhook delivery on GitHub: Settings → Webhooks → Recent Deliveries
- Ensure webhook URL is correct
- Verify the webhook is active (green checkmark)

### Invalid composer.json

Common issues:
- Invalid JSON syntax
- Missing required fields (name, description, license)
- Invalid package name format (must be `vendor/package`)
- Invalid version constraints

### Packagist Shows Old Version

- Manually update: Click "Update" on your package page
- Check that you pushed the tag: `git push --tags`
- Verify webhook is working

## Best Practices

1. **Always use Git tags** for versions
2. **Keep CHANGELOG.md updated** for every release
3. **Write migration guides** for breaking changes
4. **Use semantic versioning** consistently
5. **Test installation** before announcing releases
6. **Document breaking changes** clearly
7. **Maintain backward compatibility** when possible

## Package Statistics & Monitoring

After publication, you can:

1. **View download statistics** on Packagist
2. **Monitor issues** on GitHub
3. **Track stars** on GitHub
4. **Set up notifications** for issues and PRs
5. **Add badges** to README for stats

## Promoting Your Package

After publishing:

1. **Submit to Laravel News** - https://laravel-news.com/submit
2. **Share on Twitter/X** with #Laravel hashtag
3. **Post on Reddit** - r/laravel
4. **Share in Laravel Discord**
5. **Write a blog post** about your package
6. **Add to Laravel packages list** - https://github.com/chiraggude/awesome-laravel

## Next Steps

✅ Your package is now published!

Recommended next steps:
1. Add GitHub Actions for automated testing
2. Set up Codecov for code coverage tracking
3. Add more comprehensive tests
4. Create video tutorial for usage
5. Write detailed blog post
6. Gather user feedback and iterate

## Support & Questions

If you encounter any issues:
- Check Packagist documentation: https://packagist.org/about
- GitHub Docs on releases: https://docs.github.com/en/repositories/releasing-projects-on-github
- Laravel package development: https://laravel.com/docs/packages

---

**Congratulations on publishing your first Laravel package! 🎉**

