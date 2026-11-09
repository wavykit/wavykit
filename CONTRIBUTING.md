# Contributing to WavyKit

**WavyKit is not accepting pull requests yet.** The public API is still being
finalized before the first release, so pull requests are limited to
maintainers for now. Feedback is welcome: please open an issue on this
repository with questions, bug reports, or how you would use the packages.
This section will change when contributions open.

WavyKit is developed in this monorepo. Each package in `packages/` is published
through a read-only split repository, which closes pull requests automatically;
open issues here instead.

## Development

Requires PHP 8.3+ and Composer 2. From the repository root:

```sh
composer install
composer check            # every package
composer check core       # one package
composer format [package] # apply Pint formatting
```

`composer check` validates the manifests and shared dependency constraints, then
runs Pint, PHPStan at maximum level, and Pest in each package. CI runs the same
checks on PHP 8.3, 8.4, and 8.5 with lowest and stable dependency resolutions.
See [docs/monorepo.md](docs/monorepo.md) for the layout and release process.

## Design guidelines

1. **Describe, don't execute.** WavyKit records identities, relationships,
   labels, and availability. Routes, controllers, jobs, authorization, and
   rendering stay with the application.
2. **Framework at the edges, plain PHP in the model.** WavyKit targets Laravel
   only, but builders, definitions, and registries make no Laravel calls;
   Laravel-specific code lives in readers, the request context, and service
   providers. This keeps the model easy to test; it is not a portability
   promise.
3. **Fluent builders for declarations, immutable definitions after
   registration, side-effect-free readers.** Builder state is private; one
   name per concept across builders, definitions, and output.
4. **SOLID, applied pragmatically.** One responsibility per class: builders
   declare, registries store and validate, readers resolve. Depend on an
   abstraction only when a second implementation or a test seam exists.
5. **DRY for knowledge, not for shape.** Each rule has one source of truth.
   Similar-looking code is merged when a third case appears, not before.
6. **No speculative extension points.** No plugin systems, generic metadata
   bags, or events without a concrete consumer.
7. **Fail early and clearly.** Validate at registration; throw
   `InvalidDefinition` or `ResolutionFailed`; name the page, menu, or entry in
   every message.
8. **Every public addition is deliberate.** Each public class or method is
   documented, tested, and either supported or marked `@internal`.

## Pull requests

These rules apply to maintainers now, and to everyone once contributions open.

- Keep each pull request to one change, with tests for behavior changes.
- Keep formatting separate from behavior changes.
- Update the affected package's README and the Unreleased section of its
  CHANGELOG when public behavior changes.
- Use commit subjects of the form `type: short description`, for example
  `fix: keep ancestors active for excluded pages`.

## Security

Please do not report security vulnerabilities in public issues. Use GitHub's
private vulnerability reporting on this repository instead.

## License

Contributions are released under the [MIT License](LICENSE).
