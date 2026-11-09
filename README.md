# WavyKit

WavyKit develops composable Laravel application-experience components. The
development monorepo currently contains [Pages](packages/pages/README.md), an
unreleased page registry for breadcrumbs and navigation.

## Development

Each package owns its Composer dependencies, documentation, and checks. For
Pages, see its [development instructions](packages/pages/README.md#development):

```sh
cd packages/pages
composer install
composer check
```

There is no shared installation. Packages are released together with lockstep
`vX.Y.Z` tags and distributed through read-only split repositories; CI runs
every package's checks. See [Monorepo layout and releases](docs/monorepo.md)
for the layout, release workflow, and the one-time setup that remains. No
remote, split repository, or publication exists yet.

## Ownership

WavyKit maintainers own this repository. Pages was brought into the monorepo
with its existing Git history; its Composer identity remains `wavykit/pages`.
Publishing and repository distribution require separate decisions and approval.
