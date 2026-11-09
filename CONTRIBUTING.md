# Contributing to WavyKit

WavyKit is developed in this monorepo. Each package in `packages/` is published
through a read-only split repository, which closes pull requests automatically;
open issues and pull requests here instead.

## Development

Requires PHP 8.3+ and Composer 2. From the repository root:

```sh
composer install
composer check            # every package
composer check pages      # one package
composer format [package] # apply Pint formatting
```

`composer check` validates the manifests and shared dependency constraints, then
runs Pint, PHPStan at maximum level, and Pest in each package. CI runs the same
checks on PHP 8.3, 8.4, and 8.5 with lowest and stable dependency resolutions.
See [docs/monorepo.md](docs/monorepo.md) for the layout and release process.

## Pull requests

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
