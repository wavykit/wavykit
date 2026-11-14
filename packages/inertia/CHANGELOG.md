# Changelog

## Unreleased

### Added

- `WavyKitProps`, an Inertia prop that shares the current page ID, breadcrumbs,
  and named menus from `wavykit/core` under a key the application chooses. It
  resolves at render time, after route-model binding, and is skipped by partial
  reloads that exclude it.
- Hand-written TypeScript types for the shared prop in
  `resources/types/wavykit.d.ts`, generic over menu names.
