# Changelog

## Unreleased

No changes yet.

## 0.1.0 - 2026-11-09

First release.

### Added

- Laravel 13 page registration with stable IDs and existing named-route associations.
- Nested and separately contributed definitions with parent relationships and sibling ordering.
- Resource-aware labels, explicit route-parameter mapping with `routeParameters()`, and a shared request context (`WavyKit\Context`) with the active page, bound parameters, and current user.
- Breadcrumb trails as `Breadcrumb` objects, with route-less ancestors, current-page markers, icons, and URLs kept for the current page.
- Optional page icons as presentation identifiers.
- Named menus derived from the page hierarchy, with authored root order and per-menu `except()` exclusions.
- Explicitly arranged menus built from `MenuEntry` page placements and presentation groups, with label/icon overrides and authored order; `define()` returns a `DefinedMenu` for future menu-level options.
- Current and active states from page ancestry and explicit menu nesting.
- Request-time `visibleWhen()` predicates with access to the current user.
- `MenuItem` output containing resolved data only, with a `MenuItemKind` enum that serializes to `page` or `group`.
- Public active-trail, label, and URL resolution for packages that build on page output.
- `InvalidDefinition` and `ResolutionFailed` exceptions, both implementing `WavyKitException` and extending `LogicException`.
- Validation for duplicate IDs, missing or cyclic parents, conflicting parents, duplicate route associations, unknown routes, duplicate menu names, unknown page references and exclusions, and duplicate page or group placements.

### Fixed

- Resolve the active page from the current request to avoid stale page context after unmatched requests on older Laravel 13 versions.

### Developer Experience

- Public API docblocks describe lifecycle, request callbacks, exclusions, visibility, errors, and resolved output.
- Track first-release readiness in a package-owned checklist.
- Composer validation, Pint formatting, maximum-level PHPStan analysis, and Pest/Testbench tests, run from the WavyKit monorepo root.
- Integration coverage for fresh-process route caching and resource/user request isolation.
