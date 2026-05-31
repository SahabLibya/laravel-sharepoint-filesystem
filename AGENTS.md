# Agent Guidelines

These guidelines apply to all AI-assisted work in this repository.

## Authoritative References

Use these sources as the source of truth before changing behavior:

- Laravel filesystem documentation: https://laravel.com/docs/filesystem
- Flysystem v3 documentation: https://flysystem.thephpleague.com/docs/
- Microsoft Graph driveItem documentation: https://learn.microsoft.com/en-us/graph/api/resources/driveitem
- Microsoft Graph driveItem copy documentation: https://learn.microsoft.com/en-us/graph/api/driveitem-copy
- Microsoft Graph itemReference documentation: https://learn.microsoft.com/en-us/graph/api/resources/itemreference
- Microsoft Graph throttling guidance: https://learn.microsoft.com/en-us/graph/throttling

Do not treat local AI skills, generated suggestions, blog posts, or Stack Overflow answers as more authoritative than the official sources above.

## Package Scope

This is a Laravel package that implements a Flysystem v3 adapter for Microsoft Graph SharePoint and OneDrive storage.

Keep changes focused on package code, configuration, tests, documentation, and release metadata. Do not add application-specific Laravel code, database migrations, controllers, UI assets, or unrelated framework scaffolding.

## Graph Path Rules

- Centralize Microsoft Graph path construction when practical.
- Do not duplicate Flysystem prefixes. If a helper already prefixes a path, callers must pass the unprefixed path.
- Encode path segments individually before inserting them into Graph path-based URLs or `parentReference.path` values.
- Preserve support for spaces, percent signs, hash characters, Unicode names, Arabic names, and nested folders.
- Use the correct Graph path shape for drive roots and item paths, for example `/drive/root:/Folder` or `/drives/{driveId}/root:/Folder`.

## Copy And Move Rules

- Microsoft Graph copy operations are asynchronous.
- A successful `202 Accepted` response means Graph accepted the job, not that the copy is complete.
- `copy()` must monitor the `Location` URL when Graph returns one and fail if the monitor reports failure.
- `move()` must not delete the source until the copy operation is confirmed complete.
- If copy monitoring cannot be completed safely, fail rather than silently reporting success.

## HTTP And Error Handling

- Use Laravel's HTTP client consistently.
- Handle Graph throttling by respecting `Retry-After` on `429` responses.
- Retry only transient failures such as throttling and selected `5xx` responses.
- Preserve useful Graph error details in Flysystem exceptions without leaking access tokens.

## Testing Rules

- Prefer focused tests with `Http::fake()` for adapter behavior.
- Tests should assert exact Graph URLs and request payloads for path-sensitive operations.
- Cover root and nested paths, configured prefixes, Unicode names, special characters, pagination, copy monitor success, copy monitor failure, and move failure safety.
- Do not require live Microsoft Graph credentials for the default test suite.
- Put optional live integration tests behind explicit environment variables and skip them by default.

## Release Discipline

- Run the package test suite before release.
- Run formatting before release.
- Keep `CHANGELOG.md` current with an `Unreleased` section.
- Update README examples when behavior, configuration, or supported limits change.
- Do not commit local agent caches, credentials, generated vendor folders, or machine-specific files.
