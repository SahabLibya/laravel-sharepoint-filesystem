---
name: Bug report
about: Create a report to help us improve
title: '[BUG] '
labels: bug
assignees: ''
---

## Bug Description

A clear and concise description of what the bug is.

## Steps To Reproduce

1. Go to '...'
2. Configure '...'
3. Execute '...'
4. See error

## Expected Behavior

A clear and concise description of what you expected to happen.

## Actual Behavior

What actually happened instead.

## Environment

- **PHP Version:** [e.g., 8.2.0]
- **Laravel Version:** [e.g., 11.0.0]
- **Package Version:** [e.g., 1.0.0]
- **Operating System:** [e.g., Ubuntu 22.04]

## Configuration

```php
// Your disk configuration from config/filesystems.php
'sharepoint' => [
    'driver' => 'sharepoint',
    // ... (remove sensitive data)
],
```

## Code Sample

```php
// Minimal code to reproduce the issue
Storage::disk('sharepoint')->put('test.txt', 'content');
```

## Error Messages

```
Paste any error messages, stack traces, or log entries here
```

## Additional Context

Add any other context about the problem here. For example:
- Does it work with other Laravel filesystem drivers?
- Did it work in a previous version?
- Any relevant Azure/SharePoint configuration?

## Possible Solution

If you have suggestions on how to fix the bug, please share them here.

