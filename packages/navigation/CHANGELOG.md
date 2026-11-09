# Changelog

## Unreleased

### Added

- Named menus derived from WavyKit page hierarchies, with authored root order and per-menu `except()` exclusions.
- Explicitly arranged menus with page placements, presentation groups, label/icon overrides, and authored order.
- Current and active states from page ancestry and explicit menu nesting.
- Request-time `visibleWhen()` predicates with access to the current user.
- `MenuItem` output containing resolved data only.
- Validation for duplicate menu names, unknown page references and exclusions, and duplicate page or group placements.

### Developer Experience

- Public API docblocks describe registration lifecycle, exclusions, visibility, and resolved output.
- Composer validation, Pint formatting, maximum-level PHPStan analysis, and Pest/Testbench tests, run from the WavyKit monorepo root.
- Integration coverage for fresh-process route caching and user visibility isolation.
- Verified runtime installation from stripped distribution archives together with `wavykit/pages`.
