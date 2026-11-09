# WavyKit Navigation

Named Laravel menus derived from, or arranged around, WavyKit pages.

Menus read the same page definitions as breadcrumbs, so labels, URLs, and
current and active states stay consistent across the application. A menu can
follow the page hierarchy, or arrange pages its own way without changing their
breadcrumbs. Visibility is decided per request, and a hidden entry's label and
URL are never resolved.

Most applications start here: installing Navigation also installs
[`wavykit/pages`](../pages/README.md).

Package: `wavykit/navigation`. Namespace: `WavyKit\Navigation\`. Maintained by WavyKit.
Unreleased development package. The public API remains subject to change before release.

## Requirements

PHP 8.3+, Laravel / Illuminate 13, and `wavykit/pages` 0.1. No Livewire or
database requirement.

## Installation

This package has not been published yet. Once a public release is available:

```sh
composer require wavykit/navigation
```

Laravel discovers `WavyKit\Navigation\NavigationServiceProvider` and the Pages
service provider automatically. Menus reference pages registered with
`WavyKit\Pages\PageRegistry`; they never create or modify routes.

## Derived menus

Use `derive()` for a menu that follows the page hierarchy. Register menus in an
application service provider's `boot()` method, next to your pages:

```php
use WavyKit\Navigation\MenuRegistry;
use WavyKit\Pages\Page;
use WavyKit\Pages\PageContext;
use WavyKit\Pages\PageRegistry;

public function boot(PageRegistry $pages, MenuRegistry $menus): void
{
    $pages->register(
        Page::make('dashboard')->label('Dashboard')->route('dashboard'),
        Page::make('projects')->label('Projects')->route('projects.index')->children(
            Page::make('projects.create')->label('Create project')->route('projects.create'),
            Page::make('projects.show')->route('projects.show')
                ->label(fn (PageContext $context): string => $context->parameter('project')->name),
        ),
    );

    $menus->derive('primary', 'dashboard', 'projects')->except('projects.show');
}
```

Roots keep the supplied order; descendants use page sibling order. `except()`
omits pages and their subtrees from that menu only. Excluded pages keep their
breadcrumbs, can appear in other menus, and still make their ancestors active:
on a project's page, Projects is active even though the project is not listed.
Excluded branches do not run label or parameter callbacks. Exclusion is
presentation configuration, never an HTTP authorization rule. Overlapping
roots that would list a page twice are rejected.

## Explicit menus

Use `define()` when a menu needs its own nesting or ordering:

```php
use WavyKit\Navigation\Menu;

$menus->define('footer',
    Menu::page('privacy'),
    Menu::group('your-account')->label('Your account')->children(
        Menu::page('account')->label('Profile')->icon('user'),
    ),
);
```

Register the referenced pages separately, once. Each placement reuses its page's
route, label, and icon unless overridden. `Menu::icon()` and `Page::icon()`
accept optional presentation identifiers; the host chooses how to render them.
Placement labels can also be callbacks receiving `WavyKit\Pages\PageContext`.

Explicit entries follow their authored order and include only their declared
children. They do not import page descendants, and derived-menu exclusions do
not apply to them. A page placement may have children and remain a clickable
link. Moving a menu entry never changes page ancestry, breadcrumbs, or routes.

Menu names are unique across derived and explicit menus. A page may appear in
multiple menus, but only once within a given menu. Group IDs must also be unique
within a menu; a group may share an ID with a page because they are distinct
concepts. Missing page references and duplicate placements fail validation even
when their visibility callbacks would hide them.

## Request-time visibility

`visibleWhen()` applies to explicit page placements and groups:

```php
use WavyKit\Pages\PageContext;

$menus->define('account',
    Menu::page('account')->visibleWhen(
        fn (PageContext $context): bool => $context->user !== null,
    ),
);
```

`PageContext::$user` is Laravel's current request user, or null for a guest.
For policy-based visibility, call your application's Gate/policy checks from the
predicate. A predicate must return bool; exceptions and invalid results identify
the menu and entry. No user data or visibility result is cached on the shared
registry.

A hidden entry's label, destination URL, and descendants are not resolved, so
visibility can guard entries whose labels or URLs need unavailable resource
parameters. An empty group's label is not resolved. Callbacks must use the
supplied request context rather than capturing a user at registration time.
Hiding an entry never enforces HTTP authorization: keep application middleware
and policy checks on routes and controllers.

## Reading menus

Inject `WavyKit\Navigation\Navigation` into a controller or resolve it in a view:

```php
$items = app(\WavyKit\Navigation\Navigation::class)->menu('primary');
```

`menu()` returns `MenuItem` objects with nullable `pageId`, `label`, nullable
`url`, `current`, `active`, recursive `children`, nullable `icon`, and `kind`
(`page` or `group`). Presentation groups have no page ID or URL; route-less
pages have a null URL. Labels are resolved plain strings.

`current` identifies an exact page match. `active` includes that match and the
active page's ancestors, even when the active page is absent from the menu.
Explicit menu parents also become active when a visible child entry is active.
Groups are never current. Empty explicit groups are omitted; linked page entries
remain when all of their children are hidden.

An unregistered active route still renders global menus, with no active items.
Unknown menus raise errors. Page labels and URLs follow the Pages rules,
including parameter mapping and errors that identify the page and route.

### Rendering

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

## Lifecycle and route caching

Menu registration snapshots builders. The first menu read finalizes the page
registry, then validates and freezes menus; register everything before that
read. A failed validation leaves menus open for correction. Calling `except()`
after menus are resolved fails.

Keep menu registration in a service provider that runs on every boot, including
boots that restore cached routes. Laravel's route cache stores route actions,
not menus, and label and visibility callbacks are never serialized into it. The
integration fixture generates the cache with the real `route:cache` command in
separate PHP processes and compares derived and explicit menus, bound-resource
labels and URLs, and user visibility before and after caching.

## Development

Navigation is developed from the WavyKit monorepo root, which installs the
development tools once and links this package and Pages through path
repositories:

```sh
composer install
composer check navigation
```

The package keeps its own `phpunit.xml`, `phpstan.neon`, and `pint.json`.
`composer check navigation` validates this manifest, checks Pint formatting,
runs PHPStan at maximum level on source, and executes Pest unit, Testbench
feature, and route-cache integration tests. Monorepo CI runs the checks on PHP
8.3–8.5 with lowest and stable resolutions, and has passed on all six
combinations; the lowest set emits the Symfony Translation
deprecation documented by Pages. A separate Laravel 13 / Inertia 3 / React
consumer trial passes its application checks with Pages and Navigation, and its
menu output is identical with and without cached routes.

## Versioning

Navigation follows the same 0.x policy as Pages and is released in lockstep
with it: tags are `vMAJOR.MINOR.PATCH` on the WavyKit monorepo, mirrored to the
read-only `navigation` split repository. Composer derives versions from those
tags. The release process is documented in the monorepo's `docs/monorepo.md`;
contribute there, not to the split repository.

See [the changelog](CHANGELOG.md) and the
[first release checklist](docs/release-checklist.md).
