# WavyKit Pages

A Laravel page registry for consistent page identity, hierarchy, and breadcrumbs.

Package: `wavykit/pages`. Namespace: `WavyKit\Pages\`. Maintained by WavyKit.
Unreleased development package: the planned v0.1 behavior is implemented. The public API remains subject to change before release.
Named menus built from these pages are provided by the separate
`wavykit/navigation` package.

## Requirements

PHP 8.3+ (PHP 8.x), Laravel / Illuminate 13, and Composer 2 for development.
No Livewire or database requirement.

## Installation

This package has not been published yet. Once a public release is available:

```sh
composer require wavykit/pages
```

Laravel discovers the service provider automatically. Register your application
pages as shown below; existing routes and controllers remain in place.

## Page definitions

Laravel package discovery loads `WavyKit\Pages\PagesServiceProvider`.
Register definitions in an application service provider's `boot()` method:

```php
use WavyKit\Pages\Page;
use WavyKit\Pages\PageRegistry;

public function boot(PageRegistry $pages): void
{
    $pages->register(
        Page::make('dashboard')->label('Dashboard')->route('dashboard'),
        Page::make('projects')->label('Projects')->route('projects.index')->children(
            Page::make('projects.create')
                ->label('Create project')
                ->route('projects.create'),
        ),
    );
}
```

These named routes belong to the application. Pages never creates or modifies
routes, middleware, URLs, or access rules. Definitions may be registered before
routes finish loading; resolve output only after route registration. Resolve it
after model-binding middleware if route parameters are needed for URLs.

Pages can be nested or contributed separately with `parent('projects')`.
IDs do not imply ancestry. `order(int)` controls sibling order, ascending;
ties retain declaration order. `icon(string)` stores an optional presentation
identifier that breadcrumbs, menus, or the host application can use.

Registration snapshots builders. The first registry read finalizes all definitions;
register everything before that read. Duplicate IDs, missing or cyclic
parents, conflicting parents, and duplicate route associations fail clearly.
Each page associates with at most one route and each route with at most one page.
Route-less pages are valid.

## Reading output

Inject `WavyKit\Pages\Pages` into a controller or resolve it in a view:

```php
$pages = app(\WavyKit\Pages\Pages::class);

$page = $pages->page();
$breadcrumbs = $pages->breadcrumbs();
```

`page()` returns the immutable registered `PageDefinition`, or null. It retains
dynamic callbacks rather than executing them. `context()` returns the active
definition, bound parameters, and current user. Breadcrumbs resolve their
callbacks into presentation data; they do not return live model objects.

On `projects.create`, breadcrumbs are Projects → Create project. The current
item has no link. A route-less ancestor has a null URL. Resolved labels are
plain strings.

Breadcrumbs are arrays containing `pageId`, `label`, nullable `url`, and `current`.

```blade
<nav aria-label="Breadcrumb">
    <ol>
        @foreach ($breadcrumbs as $crumb)
            <li>
                @if ($crumb['current'])
                    <span aria-current="page">{{ $crumb['label'] }}</span>
                @elseif ($crumb['url'] !== null)
                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                @else
                    <span>{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
```

Packages that build on Pages, such as Navigation, resolve page output through
the same request: `activeTrail($context)` returns the active page's ancestry,
root first; `label($page, $context)` and `url($page, $context)` resolve one
page with the rules below.

An unregistered active route returns a null page and empty breadcrumbs. Missing
route names raise errors. Ancestor URL generation forwards matching destination
path parameters only. Missing required parameters raise an exception identifying
the page and route, with Laravel's URL-generation exception preserved as the cause.

## Resource-aware labels and URLs

Labels may be strings or callbacks receiving `WavyKit\Pages\PageContext`:

```php
use App\Models\Project;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageContext;

Page::make('projects.show')
    ->route('projects.show')
    ->parent('projects')
    ->label(function (PageContext $context): string {
        $project = $context->parameter('project');

        if (! $project instanceof Project) {
            throw new \LogicException('Expected a bound project.');
        }

        return $project->name;
    })
    ->children(
        Page::make('projects.edit')->route('projects.edit')->label('Edit'),
    );
```

Register this builder with the registry as above. On the edit route, the detail
ancestor uses the bound project to resolve its label and URL.

`$pages->context()` exposes the active page and route parameters. Ancestor callbacks
see the same active page as the current breadcrumb. `parameter('name')` returns
the bound value or throws when the name is absent. Labels must return strings.
Page definitions retain callbacks; breadcrumbs contain resolved data.

If a child route uses `{item}` while an ancestor route uses `{project}`, provide
an explicit mapping on the ancestor:

```php
Page::make('projects.show')
    ->route('projects.show')
    ->parameters(fn (PageContext $context): array => [
        'project' => $context->parameter('item'),
    ]);
```

A mapping supplies the complete parameter source for that destination; it is not
merged with the active route parameters. Keys must be parameter names. Only keys
declared by the destination route are forwarded; extra keys never become query
strings. Laravel still handles bound-model route keys and URL encoding.

Callbacks run only when their output is requested, after Laravel's binding
middleware. They may run multiple times; keep them free of side effects and read
request data from the context rather than capturing a user or resource during
registration. Resolved values are not cached on the shared registry. Contexts are
request snapshots and should not be stored on application singletons.

## Development

Pages is developed from the WavyKit monorepo root, which installs the
development tools once and links this package through a path repository:

```sh
composer install
composer check pages
```

The package keeps its own `phpunit.xml`, `phpstan.neon`, and `pint.json`.
`composer check pages` validates this manifest, checks Pint formatting, runs
PHPStan at maximum level on source, and executes Pest unit and Testbench
integration tests. `composer format pages` applies formatting. The root
`composer.lock` is local and ignored; fresh checkouts resolve dependencies
within the declared constraints. Monorepo CI runs the checks on PHP 8.3–8.5
with lowest and stable resolutions.

## Route caching

Keep page registration in a service provider that runs on every boot,
including boots that restore cached routes. Do not place it inside a
`! $app->routesAreCached()` condition or rely on executing a route file to register
definitions. Laravel's route cache stores route actions, not the page registry.
Page-label and parameter callbacks remain ordinary runtime callbacks
and are not serialized into the route cache.

The integration fixture boots a small application in separate PHP processes,
generates the cache with the real `route:cache` command, then boots again from
cached routes. It compares page lookup, breadcrumbs, bound-resource URLs, and
the request user across consecutive requests. It also checks that
building the cache does not evaluate page labels.

## Verification limits

Tests cover static and bound-resource output, route-less ancestors, parameter
mapping, validation errors, request isolation, and fresh-process route-cache
round trips. The suite is also checked against a lowest-installable dependency
resolution. That older dependency set emits a Symfony Translation deprecation
under PHP 8.5; diagnostics remain visible and are not suppressed.

The suite passed on PHP 8.3.36, 8.4.26, and 8.5.12 before menus moved to
`wavykit/navigation`; since then it has run locally on PHP 8.5.12, and monorepo
CI is configured for PHP 8.3–8.5. PHP 8.3 remains the declared minimum and
dependency-resolution platform. A separate Laravel 13 / Inertia 3 / React
consumer trial, using Pages with `wavykit/navigation`, passes its application
checks, and its page output is identical with and without cached routes. These are
observed checks, not a production-readiness guarantee. Framework-neutral
compatibility is not promised.

## Versioning

The initial release is intended to remain on 0.x while the public API is refined.
Use minor releases for breaking changes during 0.x and patch releases for
compatible fixes. At 1.0 and later, breaking public API changes require a major
release. Releases are tagged `vMAJOR.MINOR.PATCH` on the WavyKit monorepo, which
versions its packages together and mirrors each tag to the read-only `pages`
split repository. Composer derives versions from those tags, so `composer.json`
does not contain a version field. The release process is documented in the
monorepo's `docs/monorepo.md`; contribute there, not to the split repository.

See [the changelog](CHANGELOG.md) for release changes and the
[first release checklist](docs/release-checklist.md) for remaining preparation. Tagging and publishing are
separate from preparing or reviewing a release.

This is an independently installable Composer package developed in the WavyKit
monorepo, with its own dependencies and checks. No workspace CLI is needed to
develop or consume it.
