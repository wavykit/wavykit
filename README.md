# WavyKit

One description of your Laravel app's experience — where users are, how they
move around, and what they see — that every part of the interface reads.

Laravel has primitives for handling requests, but none for the experience
around them, so route names, breadcrumb arrays, menu configuration, and page
headings tend to drift apart. WavyKit starts with pages: each page is defined
once, with a stable ID, its place in the hierarchy, a label, and the named
route it belongs to. Breadcrumbs and menus read from that definition, so
labels, URLs, and active states always agree. Routes, controllers,
authorization, and rendering stay with your application, and everything
returns plain data for Blade, Inertia, or any other frontend.

## Getting started

```sh
composer require wavykit/core
```

The core provides pages, breadcrumbs, and menus. The first release is
`v0.1.0`, for Laravel 13 and PHP 8.3+. Pull requests are not open yet; issues
and feedback are welcome (see [CONTRIBUTING.md](CONTRIBUTING.md)).

| Package | Purpose |
| --- | --- |
| [`wavykit/core`](packages/core/README.md) | Pages (identity, hierarchy, route association, request context), breadcrumbs, and menus. |

Integrations that need other libraries, starting with Inertia, will be
separate packages.

See [Direction and decisions](docs/decisions.md) for package boundaries,
supported versions, and what is planned next.

## Development

Development runs from this root. One install links every package from
`packages/*`, and each package keeps its own runtime dependencies,
documentation, and tool configuration:

```sh
composer install
composer check        # or: composer check core
```

Packages are released together with lockstep `vX.Y.Z` tags and distributed
through read-only split repositories; CI runs every package's checks. See [Monorepo layout and releases](docs/monorepo.md)
for the layout, release workflow, and the one-time setup that remains. The
monorepo and split repositories are public on GitHub; nothing has been
published to Packagist yet.

## Ownership

WavyKit maintainers own this repository. The core began as the Pages package,
brought into the monorepo with its existing Git history; menus were developed
as a separate Navigation package and merged back into the core before the
first release.
Publishing and repository distribution require separate decisions and approval.
