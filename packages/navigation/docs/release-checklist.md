# First release checklist

Working checklist for the first public release, in lockstep with `wavykit/pages`.
Proposed version: `v0.1.0`; no version entry, tag, or publication is created by
this checklist. Checked items record completed work; repeat compatibility checks
if the implementation or dependency constraints change.

## Local readiness

- [x] Keep scope to named menus over registered pages; Pages owns page identity and breadcrumbs, and the application owns routing and authorization.
- [x] Document registration lifecycle, exclusions, visibility, and output semantics in public API docblocks.
- [x] Provide installation, derived and explicit menu, visibility, rendering, and route-cache examples in the README.
- [x] Include MIT license, matching Composer identity, and an Unreleased changelog.
- [x] Run the root `composer check` on PHP 8.5 with stable and lowest-installable resolutions (28 tests, 138 assertions).
- [x] Check stripped archives of Navigation and Pages install together, autoload, and register both providers through package discovery.
- [x] Verify the suite on PHP 8.3, 8.4, and 8.5 with lowest and stable resolutions (monorepo CI passed on 2026-11-09).
- [x] Validate the separate Laravel/Inertia consumer trial against the split packages, including route-cache round trips.

## Repository and release preparation

- [ ] Create the read-only `navigation` split repository on GitHub and grant the split token access when authorized (repository created on 2026-11-09; the token remains).
- [ ] Confirm CI passes on GitHub and the split repository receives `main`.
- [ ] Confirm the initial version and move Unreleased changes into a dated release entry.
- [ ] Rebuild and inspect the final committed distribution.

## Publication

- [ ] Create and push the lockstep version tag on the monorepo when authorized; confirm the split workflow mirrors it to `navigation`.
- [ ] Register the `navigation` split repository on Packagist and verify installation from the published version when authorized.
- [ ] Update the README's unpublished status with the release.

Repository creation, workflow additions, commits, pushes, tags, and publication
are separate actions requiring explicit authorization.
