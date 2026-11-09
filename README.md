# WavyKit

Composable Laravel components built on one shared map of your application's
pages.

Each page is defined once, with a stable ID, its place in the hierarchy, a
label, and the named route it belongs to. Every WavyKit component reads from
that definition: breadcrumbs come from Pages and menus from Navigation, so
labels, URLs, and active states always agree. Routes, controllers,
authorization, and rendering stay with your application. Components return
plain data for Blade, Inertia, or any other frontend.

## Getting started

Most applications start with menus and breadcrumbs together:

```sh
composer require wavykit/navigation
```

Navigation requires Pages, so this installs both. For breadcrumbs only, install
`wavykit/pages` on its own. Both packages are unreleased; the first release is
planned as `v0.1.0` for Laravel 13 and PHP 8.3+.

| Package | Purpose |
| --- | --- |
| [`wavykit/pages`](packages/pages/README.md) | The foundation: page identity, hierarchy, route association, request context, and breadcrumbs. |
| [`wavykit/navigation`](packages/navigation/README.md) | Named menus derived from, or arranged around, registered pages. Requires Pages. |

See [Direction and decisions](docs/decisions.md) for package boundaries,
supported versions, and what is planned next.

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
for the layout, release workflow, and the one-time setup that remains. The
monorepo and split repositories are public on GitHub; nothing has been
published to Packagist yet.

## Ownership

WavyKit maintainers own this repository. Pages was brought into the monorepo
with its existing Git history; its Composer identity remains `wavykit/pages`.
Navigation was extracted from Pages inside the monorepo.
Publishing and repository distribution require separate decisions and approval.
