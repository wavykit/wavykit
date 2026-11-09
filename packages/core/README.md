# WavyKit Core

One description of your Laravel application's experience: its pages, and the
breadcrumbs and menus that read from them.

Each page is defined once, with a stable ID, a place in the hierarchy, a label
that can come from the bound resource, and an optional association with an
existing named route. The ID is separate from the route, so a page keeps its
identity when a URL changes, and a page can exist without a route. Breadcrumbs
and menus read the same definitions, so labels, URLs, and current and active
states stay consistent. A menu can follow the page hierarchy, or arrange pages
its own way without changing their breadcrumbs.

WavyKit describes; your application executes. Routes, controllers,
authorization, and rendering stay with the application, and everything here
returns plain data for Blade, Inertia, or any other frontend.

Package: `wavykit/core`. Namespaces: `WavyKit\Pages\` (pages),
`WavyKit\Navigation\` (breadcrumbs and menus), and `WavyKit\Context`.
Maintained by WavyKit. Version 0.1.0 is the first release. While WavyKit is on
0.x, minor releases may change the public API; see [Versioning](#versioning).

## Requirements

PHP 8.3+, Laravel / Illuminate 13, and Composer 2 for development. No Livewire
or database requirement.

## Installation

Install with Composer:

```sh
composer require wavykit/core
```

Laravel discovers `WavyKit\CoreServiceProvider` automatically. Register your
pages and menus as shown below; existing routes and controllers remain in
place.

## Pages

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

These named routes belong to the application. WavyKit never creates or
modifies routes, middleware, URLs, or access rules. Definitions may be
registered before routes finish loading; resolve output only after route
registration, and after model-binding middleware if route parameters are needed
for URLs.

Pages can be nested or contributed separately with `parent('projects')`.
IDs do not imply ancestry. `order(int)` controls sibling order, ascending;
ties retain declaration order. `icon(string)` stores an optional presentation
identifier that breadcrumbs, menus, or the host application can use.

Registration snapshots builders. The first registry read finalizes all
definitions; register everything before that read. Duplicate IDs, missing or
cyclic parents, conflicting parents, and duplicate route associations fail
clearly. Each page associates with at most one route and each route with at
most one page. Route-less pages are valid.

### Reading the current page

Inject `WavyKit\Pages\Pages` into a controller or resolve it in a view:

```php
$pages = app(\WavyKit\Pages\Pages::class);

$page = $pages->current();
$context = $pages->context();
```

`current()` returns the immutable registered `PageDefinition` for the current
route, or null. It retains dynamic callbacks rather than executing them.
`context()` returns a `WavyKit\Context` with the active definition, bound route
parameters, and current user.

Packages that build on the core resolve page output through the same request:
`activeTrail($context)` returns the active page's ancestry, root first;
`label($page, $context)` and `url($page, $context)` resolve one page with the
rules below.

### Resource-aware labels and URLs

Labels may be strings or callbacks receiving `WavyKit\Context`:

```php
use App\Models\Project;
use WavyKit\Context;
use WavyKit\Pages\Page;

Page::make('projects.show')
    ->route('projects.show')
    ->parent('projects')
    ->label(function (Context $context): string {
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

On the edit route, the detail ancestor uses the bound project to resolve its
label and URL. Ancestor callbacks see the same active page as the current
breadcrumb. `parameter('name')` returns the bound value or throws when the name
is absent. Labels must return strings.

If a child route uses `{item}` while an ancestor route uses `{project}`, provide
an explicit mapping on the ancestor:

```php
Page::make('projects.show')
    ->route('projects.show')
    ->routeParameters(fn (Context $context): array => [
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
registration. Resolved values are not cached on the shared registries. Contexts
are request snapshots and should not be stored on application singletons.

An unregistered active route returns a null page. Missing route names raise
errors. Ancestor URL generation forwards matching destination path parameters
only. Missing required parameters raise an exception identifying the page and
route, with Laravel's URL-generation exception preserved as the cause.

## Breadcrumbs

Inject `WavyKit\Navigation\Navigation` into a controller or resolve it in a
view:

```php
$breadcrumbs = app(\WavyKit\Navigation\Navigation::class)->breadcrumbs();
```

On `projects.create`, breadcrumbs are Projects → Create project. Each is a
`WavyKit\Navigation\Breadcrumb` with `pageId`, `label`, nullable `url`,
`current`, and nullable `icon`, the same shape as menu items. Every breadcrumb
keeps its URL, including the current one, so the renderer decides whether to
link it; route-less ancestors have no URL. Labels are resolved plain strings,
and an unregistered active route returns empty breadcrumbs.

```blade
<nav aria-label="Breadcrumb">
    <ol>
        @foreach ($breadcrumbs as $crumb)
            <li>
                @if ($crumb->current)
                    <span aria-current="page">{{ $crumb->label }}</span>
                @elseif ($crumb->url !== null)
                    <a href="{{ $crumb->url }}">{{ $crumb->label }}</a>
                @else
                    <span>{{ $crumb->label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
```

## Menus

### Derived menus

Use `derive()` for a menu that follows the page hierarchy, registered next to
your pages:

```php
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Pages\PageRegistry;

public function boot(PageRegistry $pages, MenuRegistry $menus): void
{
    // ... register pages, including projects.show as above

    $menus->derive('primary', 'dashboard', 'projects')->except('projects.show');
}
```

Roots keep the supplied order; descendants use page sibling order. `except()`
omits pages and their subtrees from that menu only. Excluded pages keep their
breadcrumbs, can appear in other menus, and still make their ancestors active:
on a project's page, Projects is active even though the project is not listed.
Excluded branches do not run label or parameter callbacks. Exclusion is
presentation configuration, never an HTTP authorization rule. Overlapping
roots that would list a page twice are rejected. Derived menus have no
request-time visibility; use an explicit menu when entries depend on the user.

### Explicit menus

Use `define()` when a menu needs its own nesting or ordering. Each
`MenuEntry` places a registered page or groups other entries:

```php
use WavyKit\Navigation\MenuEntry;

$menus->define('footer',
    MenuEntry::page('privacy'),
    MenuEntry::group('your-account')->label('Your account')->children(
        MenuEntry::page('account')->label('Profile')->icon('user'),
    ),
);
```

Register the referenced pages separately, once. Each placement reuses its page's
route, label, and icon unless overridden. Placement labels can also be callbacks
receiving `WavyKit\Context`.

Explicit entries follow their authored order and include only their declared
children. They do not import page descendants, and derived-menu exclusions do
not apply to them. A page placement may have children and remain a clickable
link. Moving a menu entry never changes page ancestry, breadcrumbs, or routes.

Menu names are unique across derived and explicit menus. A page may appear in
multiple menus, but only once within a given menu. Group IDs must also be unique
within a menu; a group may share an ID with a page because they are distinct
concepts. Missing page references and duplicate placements fail validation even
when their visibility callbacks would hide them.

### Request-time visibility

`visibleWhen()` applies to explicit page placements and groups:

```php
use WavyKit\Context;

$menus->define('account',
    MenuEntry::page('account')->visibleWhen(
        fn (Context $context): bool => $context->user !== null,
    ),
);
```

`Context::$user` is Laravel's current request user, or null for a guest. For
policy-based visibility, call your application's Gate/policy checks from the
predicate. A predicate must return bool; exceptions and invalid results identify
the menu and entry. No user data or visibility result is cached on the shared
registry.

A hidden entry's label, destination URL, and descendants are not resolved, so
visibility can guard entries whose labels or URLs need unavailable resource
parameters. An empty group's label is not resolved. Hiding an entry never
enforces HTTP authorization: keep application middleware and policy checks on
routes and controllers.

### Reading menus

```php
$items = app(\WavyKit\Navigation\Navigation::class)->menu('primary');
```

`menu()` returns `MenuItem` objects with nullable `pageId`, `label`, nullable
`url`, `current`, `active`, recursive `children`, nullable `icon`, and `kind`,
a `MenuItemKind` enum that serializes to `page` or `group`. As with
breadcrumbs, the current page keeps its URL. Presentation groups have no page ID or URL; route-less
pages have a null URL.

`current` identifies an exact page match. `active` includes that match and the
active page's ancestors, even when the active page is absent from the menu.
Explicit menu parents also become active when a visible child entry is active.
Groups are never current. Empty explicit groups are omitted; linked page entries
remain when all of their children are hidden. An unregistered active route
still renders global menus, with no active items. Unknown menus raise errors.

For example, create `resources/views/partials/menu.blade.php`:

```blade
<ul>
    @foreach ($items as $item)
        <li @class(['active' => $item->active])>
            @if ($item->url !== null)
                <a href="{{ $item->url }}" @if ($item->current) aria-current="page" @endif>
                    {{ $item->label }}
                </a>
            @else
                <span>{{ $item->label }}</span>
            @endif
            @if ($item->children !== [])
                @include('partials.menu', ['items' => $item->children])
            @endif
        </li>
    @endforeach
</ul>
```

Render it inside an appropriately labelled navigation element with
`@include('partials.menu', ['items' => $navigation->menu('primary')])`.

## Errors

Every exception implements `WavyKit\Exceptions\WavyKitException` and extends
`LogicException`:

- `InvalidDefinition`: a page or menu declaration is invalid, was registered
  after the registries were read, or references an unknown route, parent, or
  page.
- `ResolutionFailed`: output could not be resolved for the request, such as a
  failing or invalid label, route-parameter, or visibility callback, a missing
  route parameter, a URL generation failure, or an unknown menu. The original
  exception is kept as the previous exception.

Messages name the page, menu, or entry involved.

## Lifecycle and route caching

Menu registration snapshots builders. The first menu read finalizes the page
registry, then validates and freezes menus; register everything before that
read. A failed menu validation leaves menus open for correction. Calling
`except()` after menus are resolved fails.

Keep page and menu registration in a service provider that runs on every boot,
including boots that restore cached routes. Do not place it inside a
`! $app->routesAreCached()` condition or rely on executing a route file to
register definitions. Laravel's route cache stores route actions, not the
registries, and label, parameter, and visibility callbacks are never serialized
into it.

The integration fixture boots a small application in separate PHP processes,
generates the cache with the real `route:cache` command, then boots again from
cached routes. It compares page lookup, breadcrumbs, derived and explicit
menus, bound-resource labels and URLs, and user visibility across consecutive
requests. It also checks that building the cache does not evaluate page labels.

## Development

The core is developed from the WavyKit monorepo root, which installs the
development tools once and links this package through a path repository:

```sh
composer install
composer check core
```

The package keeps its own `phpunit.xml`, `phpstan.neon`, and `pint.json`.
`composer check core` validates this manifest, checks Pint formatting, runs
PHPStan at maximum level on source, and executes Pest unit, Testbench feature,
and route-cache integration tests. `composer format core` applies formatting.
The root `composer.lock` is local and ignored; fresh checkouts resolve
dependencies within the declared constraints.

## Verification limits

Tests cover static and bound-resource output, route-less ancestors, parameter
mapping, derived and explicit menus, exclusions, visibility, validation errors,
request isolation, and fresh-process route-cache round trips. The suite is also
checked against a lowest-installable dependency resolution. That older
dependency set emits a Symfony Translation deprecation under PHP 8.5;
diagnostics remain visible and are not suppressed.

Monorepo CI runs the checks on PHP 8.3, 8.4, and 8.5 with lowest and stable
resolutions. PHP 8.3 remains the declared minimum and dependency-resolution
platform. The private WavyKit playground, a Laravel 13 / Inertia 3 / React
application, consumes the package and checks that its output is identical with
and without cached routes. These are observed checks, not a production-readiness
guarantee.

## Versioning

The initial release is intended to remain on 0.x while the public API is refined.
Use minor releases for breaking changes during 0.x and patch releases for
compatible fixes. At 1.0 and later, breaking public API changes require a major
release. Releases are tagged `vMAJOR.MINOR.PATCH` on the WavyKit monorepo, which
versions its packages together and mirrors each tag to the read-only `core`
split repository. Composer derives versions from those tags, so `composer.json`
does not contain a version field. The release process is documented in the
monorepo's `docs/monorepo.md`; contribute there, not to the split repository.

See [the changelog](CHANGELOG.md) for release changes. The
[first release checklist](docs/release-checklist.md) records how `v0.1.0` was
prepared.
