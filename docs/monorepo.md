# Monorepo layout and releases

WavyKit is developed in this repository and distributed as independent Composer
packages through read-only split repositories, the same model Laravel uses for
its `illuminate/*` components.

## Layout

Each package lives in `packages/<name>/` and is self-contained:

- its own `composer.json` (runtime `require` plus its own `require-dev`),
  tests, Pint/PHPStan configuration, README, CHANGELOG, and LICENSE;
- a `.gitattributes` that export-ignores development files from dist archives;
- `.github/workflows/close-pull-request.yml`, which only runs in the split
  repository and redirects contributors here.

There is no root `composer.json` and no shared install. Run checks inside each
package with `composer install && composer check`.

## Naming

Every package is published under the `wavykit/` Composer vendor and the
`WavyKit\` PHP namespace, and its folder matches its package name.

- **Components** have generic, descriptive names for the capability they
  provide: `wavykit/pages` (`WavyKit\Pages\`), and later names such as
  navigation or actions.
- **Composed products**, which combine components with their own integration
  code and conventions, have brand names: for example `wavykit/current`
  (`WavyKit\Current\`).

## Layout choice

We use `packages/<name>/` rather than Laravel's `src/Illuminate/<Name>`. That
layout suits a root framework package that `replace`s every component; WavyKit
has no such package, and each component is meant to be installed on its own.

## Distribution

The [split workflow](../.github/workflows/split.yml) runs on pushes to `main`
and on `v*` tags. For each folder in `packages/`, it runs
`git subtree split --prefix=packages/<name>` and pushes the result to
`<owner>/<name>` on GitHub, where `<owner>` is this repository's owner:

- a push to `main` updates the split repository's `main` branch;
- a `vX.Y.Z` tag is pushed to the split repository as the same tag.

Packagist reads each split repository, so `composer.json` sits at its root and
Composer derives versions from its tags. Contributions go to this repository;
split repositories are read-only and close pull requests automatically.

The split repository's history starts at the commit that first placed the
package under `packages/<name>/`; earlier history remains in this repository.

## Versioning

Versions are lockstep: one `vMAJOR.MINOR.PATCH` tag on this repository releases
every package at that version. Each package's CHANGELOG records its own
changes. Revisit independent, package-prefixed tags before 1.0 or when packages
start releasing on different schedules.

## Continuous integration

The [tests workflow](../.github/workflows/tests.yml) runs `composer check` in
every package on PHP 8.3, 8.4, and 8.5, with lowest and stable dependency
resolutions. New packages are discovered automatically.

## Adding a package

1. Create `packages/<name>/` with the files listed under [Layout](#layout).
2. Create the empty read-only repository `<owner>/<name>` and grant the split
   token write access to it before the next push to `main`.
3. If the package depends on another WavyKit package, add a `path` repository
   pointing at `../<dependency>` to the dependent package's `composer.json` so
   development uses the local copy. Consider `symplify/monorepo-builder` for
   dependency-version validation once several packages depend on each other.
4. Add the package to the Development section of the root README.

## One-time setup

These steps are not done yet, and each needs separate approval:

1. Create the monorepo on GitHub as `<owner>/wavykit` and push `main`.
2. Create a read-only repository `<owner>/<name>` for each package, without
   initial commits.
3. Create a fine-grained token (or GitHub App) with contents write access to
   the split repositories only, and store it as the `SPLIT_TOKEN` Actions
   secret on the monorepo.
4. Register each split repository on Packagist when the package is published.
