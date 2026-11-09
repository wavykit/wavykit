# First release checklist

Working checklist for the first public release of `wavykit/core`. Proposed
version: `v0.1.0`; no version entry, tag, or publication is created by this
checklist. Checked items record completed work; compatibility checks must be
repeated when the implementation or dependency constraints change.

## Local readiness

- [x] Keep scope to pages, breadcrumbs, and menus; the application owns routing, rendering, and authorization.
- [x] Document registration lifecycle, callbacks, exclusions, visibility, errors, and output semantics in public API docblocks.
- [x] Provide installation, registration, breadcrumb, menu, visibility, rendering, resource, and route-cache examples in the README.
- [x] Include MIT license, matching Composer identity, and an Unreleased changelog.
- [x] Restructure Pages and Navigation into `wavykit/core` (WK-018) and run the root `composer check` (PHP 8.5: 51 tests, 208 assertions).
- [ ] Decide the public API in the API review (WK-012) and implement agreed changes.
- [x] Verify the suite on PHP 8.3, 8.4, and 8.5 with lowest and stable resolutions after the restructure (monorepo CI passed on `b870f39`, 2026-11-09).
- [x] Check a stripped archive's runtime installation, class autoloading, provider discovery, and page and menu behavior after the restructure (2026-11-09, with `laravel/framework` in a scratch application).
- [x] Validate the playground consumer, including route-cache round trips, after the restructure (2026-11-09).
- [ ] Validate the playground consumer again after WK-012.

## Repository and release preparation

- [x] Choose the distribution model: developed in the WavyKit monorepo, published through a read-only split repository with lockstep monorepo tags (see the monorepo's `docs/monorepo.md`).
- [x] Add monorepo CI for PHP 8.3–8.5 with lowest and stable dependency resolutions.
- [x] Set up the read-only `core` split repository and add it to the split GitHub App (2026-11-09; its history starts at the restructure commit).
- [ ] Delete the former `pages` and `navigation` split repositories and remove them from the split GitHub App.
- [x] Confirm CI passes on GitHub and the `core` split repository receives `main` (2026-11-09, `b870f39`).
- [ ] Confirm the initial version and move Unreleased changes into a dated release entry.
- [ ] Rebuild and inspect the final committed distribution, including new documentation and changelog.
- [ ] Record final checks and verify that the intended release commit has a clean working tree.

## Publication

- [ ] Merge/push the reviewed branch when authorized.
- [ ] Create and push the version tag on the monorepo when authorized; confirm the split workflow mirrors it to the `core` repository.
- [ ] Register the `core` split repository on Packagist and verify installation from the published version when authorized.
- [ ] Create the public `wavykit/.github` repository with the org profile, `SECURITY.md`, and a code of conduct when authorized.
- [ ] Update the README's unpublished status with the release.

Repository creation, workflow additions, commits, pushes, tags, and publication
are separate actions requiring explicit authorization. Use the package's
[versioning policy](../README.md#versioning); no speculative feature is required
for this first release.
