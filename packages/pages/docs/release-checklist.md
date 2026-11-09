# First release checklist

Working checklist for the first public release. Proposed version: `v0.1.0`;
no version entry, tag, or publication is created by this checklist. Checked items
record completed work; historical compatibility checks must be repeated if the
implementation or dependency constraints change.

## Local readiness

- [x] Keep scope to page metadata, breadcrumbs, and named menus; application owns routing and authorization.
- [x] Review the public API against the prototype and consumer usage.
- [x] Document registration lifecycle, callbacks, errors, visibility, and output semantics in public API docblocks.
- [x] Provide installation, registration, rendering, resource, and route-cache examples in the README.
- [x] Include MIT license, matching Composer identity, and an Unreleased changelog.
- [x] Verify the complete suite on PHP 8.3, 8.4, and 8.5 (see [verification limits](../README.md#verification-limits)).
- [x] Check a lowest-installable dependency resolution; retain its documented deprecation caveat.
- [x] Validate a separate Laravel/Inertia consumer, including route-cache round trips.
- [x] Check a stripped archive's runtime installation, class autoloading, and hierarchy/menu behavior.
- [x] Run current Composer checks after the API documentation pass and review the diff (PHP 8.5: 43 tests, 169 assertions; executable tokens unchanged).
- [ ] Commit the reviewed documentation changes when authorized.

## Repository and release preparation

- [ ] Choose the public repository location and create/configure it when authorized.
- [ ] Configure CI for PHP 8.3–8.5 and dependency resolution when authorized to add workflows.
- [ ] Confirm the initial version and move Unreleased changes into a dated release entry.
- [ ] Rebuild and inspect the final committed distribution, including new documentation and changelog.
- [ ] Record final checks and verify that the intended release commit has a clean working tree.

## Publication

- [ ] Merge/push the reviewed branch when authorized.
- [ ] Create and push the version tag when authorized.
- [ ] Register on Packagist and verify installation from the published version when authorized.
- [ ] Update the README's unpublished status with the release.

Repository creation, workflow additions, commits, pushes, tags, and publication
are separate actions requiring explicit authorization. Use the package's
[versioning policy](../README.md#versioning); no speculative feature is required
for this first release.
