# Monorepo layout and releases

WavyKit is developed in this repository and distributed as independent Composer
packages through read-only split repositories, the same model Laravel uses for
its `illuminate/*` components.

## Layout

Each package lives in `packages/<name>/` and contains:

- a runtime-only `composer.json`: `name`, `description`, `type`, `license`,
  `require`, `autoload`, and `extra.laravel`, with no repositories, stability
  settings, or development dependencies;
- its tests and `tests/Pest.php`, plus `phpunit.xml`, `phpstan.neon`, and
  `pint.json`, which use the root `vendor/`;
- README, CHANGELOG, and LICENSE;
- a `.gitattributes` that export-ignores development files from dist archives;
- `.github/workflows/close-pull-request.yml`, which only runs in the split
  repository and redirects contributors here.

Tests live with their package, as in Symfony's components, rather than in a
root `tests/` folder as in Laravel's framework. Each package can then be
checked and released on its own, and a package that depends on another tests
the two together. A root `tests/` folder is reserved for future integration
tests that span several packages.

## Development install

The root `composer.json` is `wavykit/monorepo`, a development-only manifest
that is never published. It installs every package from `packages/*` through
a path repository, at version `0.1.x-dev`, and requires each one as
`^0.1@dev`. The `@dev` flag lets only these packages resolve to development
versions; everything else stays stable, including `--prefer-lowest` runs. It
also holds the development dependencies, test autoloading, scripts, and the PHP
8.3 resolution platform. `composer.lock` is local and ignored.

```sh
composer install
composer check            # every package
composer check core       # one package
composer format [package] # apply Pint
```

[`bin/check`](../bin/check) validates the root manifest and runs
`monorepo-builder validate`, so packages cannot require shared dependencies
with different constraints. Validation always includes the root manifest, so
[monorepo-builder.php](../monorepo-builder.php) excludes the internal
`wavykit/*` names, which the root requires with `@dev`. Then, for each package, it validates the package
manifest and runs Pint, PHPStan, and Pest with that package's configuration.
Pint and PHPStan run in the package directory. Pest runs from the root,
because it resolves its root from the `vendor/` location. monorepo-builder
comes from `monorepo-php/monorepo`, its prefixed build. Its `merge` command is
not used: path repositories already install each package with its own
`require`.

## Naming

Every package is published under the `wavykit/` Composer vendor and the
`WavyKit\` PHP namespace, and its folder matches its package name.

- **Packages** have generic, descriptive names: `wavykit/core`, and
  integrations named after what they integrate, such as `wavykit/inertia`.
  Namespaces inside a package are named after the concepts they hold, such as
  `WavyKit\Pages\` and `WavyKit\Navigation\` in the core.
- **Composed products**, which combine packages with their own integration
  code and conventions, have brand names: for example `wavykit/current`
  (`WavyKit\Current\`), a working name.

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

The [tests workflow](../.github/workflows/tests.yml) installs from the root and
runs `composer check` on PHP 8.3, 8.4, and 8.5, with lowest and stable
dependency resolutions. New packages are checked automatically once the root
manifest requires them.

## Adding a package

1. Create `packages/<name>/` with the files listed under [Layout](#layout).
2. Create the empty read-only repository `<owner>/<name>` and add it to the
   split app's installation before the next push to `main`.
3. In the root `composer.json`, add the package to the path repository's
   `versions` option at `0.1.x-dev` (the current development line) and to
   `require` as `^0.1@dev`. Add its test namespace to `autoload-dev`, and add
   its name to the version-conflict exclusions in `monorepo-builder.php`.
4. If the package depends on another WavyKit package, require it with a normal
   constraint such as `^0.1`. The root path repository supplies the local copy.
5. Add the package to the root README.

## One-time setup

Steps 1–3 were done on 2026-11-09 for `wavykit/wavykit` and `wavykit/core`,
both public; the `core` split history starts at the commit that merged Pages
and Navigation into the core. The former `wavykit/pages` and
`wavykit/navigation` split repositories are to be deleted. Step 4 waits for the
first release and needs separate approval:

1. Create the monorepo on GitHub as `<owner>/wavykit` and push `main`.
2. Create a read-only repository `<owner>/<name>` for each package, without
   initial commits.
3. Create the split GitHub App and store its credentials (see
   [Split app](#split-app)).
4. Register each split repository on Packagist when the package is published.

### Split app

The split workflow authenticates as a GitHub App, so no credential expires and
nothing depends on a personal account. Each job mints a one-hour installation
token limited to its own split repository, with `actions/create-github-app-token`.

1. In the organization's settings, open Developer settings → GitHub Apps → New
   GitHub App. Name it, for example, `WavyKit Split`. Use the monorepo URL as
   the homepage, turn off the webhook, and allow installation only on this
   account.
2. Under repository permissions, grant **Contents: Read and write** and
   **Workflows: Read and write**. Split repositories contain a workflow file,
   so pushes need the second permission. Grant nothing else.
3. Install the app on the organization with **Only select repositories**, and
   choose the split repositories, not the monorepo.
4. On the monorepo, add the app's **Client ID** as the Actions *variable*
   `SPLIT_APP_CLIENT_ID`, and a generated private key as the Actions *secret*
   `SPLIT_APP_PRIVATE_KEY`:

   ```sh
   gh variable set SPLIT_APP_CLIENT_ID --repo <owner>/wavykit --body <client-id>
   gh secret set SPLIT_APP_PRIVATE_KEY --repo <owner>/wavykit < path/to/private-key.pem
   ```

   Then delete the downloaded `.pem` file. To rotate the key, generate a new
   one, update the secret, and delete the old key in the app's settings.
