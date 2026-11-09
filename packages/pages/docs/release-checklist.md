# First release checklist

Working checklist for the first public release. Proposed version: `v0.1.0`;
no version entry, tag, or publication is created by this checklist. Checked items
record completed work; historical compatibility checks must be repeated if the
implementation or dependency constraints change.

## Local readiness

- [x] Keep scope to page metadata, hierarchy, and breadcrumbs; named menus live in `wavykit/navigation`, and the application owns routing and authorization.
- [x] Review the public API against the prototype and consumer usage.
- [x] Document registration lifecycle, callbacks, errors, visibility, and output semantics in public API docblocks.
- [x] Provide installation, registration, rendering, resource, and route-cache examples in the README.
- [x] Include MIT license, matching Composer identity, and an Unreleased changelog.
- [x] Verify the complete suite on PHP 8.3, 8.4, and 8.5 after the navigation split: monorepo CI passed with lowest and stable resolutions on 2026-11-09 (see [verification limits](../README.md#verification-limits)).
- [x] Check a lowest-installable dependency resolution; retain its documented deprecation caveat.
- [x] Validate a separate Laravel/Inertia consumer, including route-cache round trips, against the split packages (repeated after the split with `wavykit/navigation`).
- [x] Check a stripped archive's runtime installation, class autoloading, and hierarchy behavior (repeated after the split, installed together with `wavykit/navigation`).
- [x] Run the root `composer check` after the navigation split (PHP 8.5: 24 tests, 79 assertions for Pages; stable and lowest-installable resolutions).
- [x] Commit the reviewed documentation changes when authorized.

## Repository and release preparation

- [x] Choose the distribution model: developed in the WavyKit monorepo, published through a read-only `pages` split repository with lockstep monorepo tags (see the monorepo's `docs/monorepo.md`).
- [x] Add monorepo CI for PHP 8.3–8.5 with lowest and stable dependency resolutions.
- [x] Create the monorepo and the read-only `pages` split repository on GitHub and configure the split GitHub App (done 2026-11-09).
- [x] Confirm CI passes on GitHub and the split repository receives `main` (2026-11-09; the split keeps the original history from `5e92cee`).
- [ ] Confirm the initial version and move Unreleased changes into a dated release entry.
- [ ] Rebuild and inspect the final committed distribution, including new documentation and changelog.
- [ ] Record final checks and verify that the intended release commit has a clean working tree.

## Publication

- [ ] Merge/push the reviewed branch when authorized.
- [ ] Create and push the version tag on the monorepo when authorized; confirm the split workflow mirrors it to the `pages` repository.
- [ ] Register the `pages` split repository on Packagist and verify installation from the published version when authorized.
- [ ] Update the README's unpublished status with the release.

Repository creation, workflow additions, commits, pushes, tags, and publication
are separate actions requiring explicit authorization. Use the package's
[versioning policy](../README.md#versioning); no speculative feature is required
for this first release.
