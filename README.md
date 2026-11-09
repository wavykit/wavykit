# WavyKit

WavyKit develops composable Laravel application-experience components. The
development monorepo contains these unreleased packages:

| Package | Purpose |
| --- | --- |
| [`wavykit/pages`](packages/pages/README.md) | Page identity, hierarchy, route association, request context, and breadcrumbs. |
| [`wavykit/navigation`](packages/navigation/README.md) | Named menus derived from, or arranged around, registered pages. Requires Pages. |

## Development

Development runs from this root. One install links every package from
`packages/*`, and each package keeps its own runtime dependencies,
documentation, and tool configuration:

```sh
composer install
composer check        # or: composer check pages navigation
```

Packages are released together with lockstep `vX.Y.Z` tags and distributed
through read-only split repositories; CI runs every package's checks. See [Monorepo layout and releases](docs/monorepo.md)
for the layout, release workflow, and the one-time setup that remains. No
remote, split repository, or publication exists yet.

## Ownership

WavyKit maintainers own this repository. Pages was brought into the monorepo
with its existing Git history; its Composer identity remains `wavykit/pages`.
Navigation was extracted from Pages inside the monorepo.
Publishing and repository distribution require separate decisions and approval.
