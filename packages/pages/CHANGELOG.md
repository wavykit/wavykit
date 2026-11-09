# Changelog

## Unreleased

### Added

- Laravel 13 page registration with stable IDs and existing named-route associations.
- Nested and separately contributed definitions with parent relationships and sibling ordering.
- Resource-aware labels, explicit route-parameter mapping, and request context.
- Breadcrumb trails with route-less ancestors and current-page markers.
- Derived and explicitly arranged named menus, label/icon overrides, and current/active states.
- Request-time menu visibility with access to the current user.
- Validation for invalid definitions, references, duplicate placements, and cycles.

### Fixed

- Resolve the active page from the current request to avoid stale page context after unmatched requests on older Laravel 13 versions.

### Developer Experience

- Public API docblocks describe lifecycle, request callbacks, menu semantics, errors, and resolved output.
- Track first-release readiness in a package-owned checklist.

- Composer validation, Pint formatting, maximum-level PHPStan analysis, and Pest/Testbench tests.
- Integration coverage for fresh-process route caching and resource/user request isolation.
- Verified the complete suite on PHP 8.3, 8.4, and 8.5, plus a separate Laravel/Inertia consumer trial.
- Verified runtime autoloading and registry/menu behavior from the stripped distribution archive.
