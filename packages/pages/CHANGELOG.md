# Changelog

## Unreleased

### Added

- Laravel 13 page registration with stable IDs and existing named-route associations.
- Nested and separately contributed definitions with parent relationships and sibling ordering.
- Resource-aware labels, explicit route-parameter mapping, and request context.
- Breadcrumb trails with route-less ancestors and current-page markers.
- Optional page icons as presentation identifiers.
- Public active-trail, label, and URL resolution for packages that build on page output, such as `wavykit/navigation`.
- Validation for duplicate IDs, missing or cyclic parents, conflicting parents, duplicate route associations, and unknown routes.

### Fixed

- Resolve the active page from the current request to avoid stale page context after unmatched requests on older Laravel 13 versions.

### Developer Experience

- Public API docblocks describe lifecycle, request callbacks, errors, and resolved output.
- Track first-release readiness in a package-owned checklist.
- Composer validation, Pint formatting, maximum-level PHPStan analysis, and Pest/Testbench tests, run from the WavyKit monorepo root.
- Integration coverage for fresh-process route caching and resource/user request isolation.
- Verified runtime autoloading and registry behavior from the stripped distribution archive.
