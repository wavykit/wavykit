# WavyKit Pages

A Laravel-first page registry for consistent breadcrumbs and navigation.

Package: `wavykit/pages`. Namespace: `WavyKit\Pages\`. Maintained by WavyKit.
Unreleased development package: the planned v0.1 behavior is implemented. The public API remains subject to change before release.

## Requirements

PHP 8.3+ (PHP 8.x), Laravel / Illuminate 13, and Composer 2 for development.
No Livewire or database requirement.

## Page definitions

Laravel package discovery loads `WavyKit\Pages\Laravel\PagesServiceProvider`.
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

    $pages->deriveMenu('primary', 'dashboard', 'projects');
}
```

These named routes belong to the application. Pages never creates or modifies
routes, middleware, URLs, or access rules. Definitions may be registered before
routes finish loading; resolve output only after route registration. Resolve it
after model-binding middleware if route parameters are needed for URLs.

Pages can be nested or contributed separately with `parent('projects')`.
IDs do not imply ancestry. `order(int)` controls sibling order, ascending;
ties retain declaration order. Menu roots retain the supplied order.

Registration snapshots builders. The first registry read finalizes all definitions
and menus; register everything before that read. Duplicate IDs, missing or cyclic
parents, conflicting parents, and duplicate route associations fail clearly.
Each page associates with at most one route and each route with at most one page.
Route-less pages are valid.

## Reading output

Inject `WavyKit\Pages\Laravel\Pages` into a controller or resolve it in a view:

```php
$pages = app(\WavyKit\Pages\Laravel\Pages::class);

$page = $pages->page();
$breadcrumbs = $pages->breadcrumbs();
$navigation = $pages->navigation('primary');
```

`page()` returns the immutable registered `PageDefinition`, or null. It retains
dynamic callbacks rather than executing them. `context()` returns the active
definition, bound parameters, and current user. Breadcrumbs and navigation resolve
their callbacks into presentation data; they do not return live model objects.

On `projects.create`, breadcrumbs are Projects → Create project. The current
item has no link. Projects is active in the menu; Create project is current
and active. A route-less ancestor has a null URL. Resolved labels are plain strings.

Breadcrumbs are arrays containing `pageId`, `label`, nullable `url`, and `current`.
Navigation returns `NavigationItem` objects with nullable `pageId`, `label`,
nullable `url`, `current`, `active`, recursive `children`, nullable `icon`, and
`kind` (`page` or `group`). Presentation groups have no page ID or URL.

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

`navigationVisible(false)` removes a page and its subtree from derived menus,
while retaining breadcrumb ancestry and ancestor active state. It is presentation
configuration, never an HTTP authorization rule. Derived menus use selected page
roots. Overlapping roots that would duplicate a page are rejected.

## Explicit named menus

Use `deriveMenu()` for a menu that follows the page hierarchy. Use `defineMenu()`
when a menu needs its own nesting or ordering:

```php
use WavyKit\Pages\Menu;

$pages->defineMenu('primary',
    Menu::group('settings')->label('Settings')->children(
        Menu::page('account'),
    ),
);

$pages->defineMenu('footer',
    Menu::page('privacy'),
    Menu::group('your-account')->label('Your account')->children(
        Menu::page('account')->label('Profile')->icon('user'),
    ),
);
```

Register the referenced pages separately, once. Each placement reuses its page's
route, label, and icon unless overridden. Both `Page::icon()` and `Menu::icon()`
accept optional presentation identifiers; the host chooses how to render them.
Placement labels can also be callbacks receiving `PageContext`.

Explicit entries follow their authored order and include only their declared
children. They do not automatically import page descendants or inherit the
page's `navigationVisible(false)` flag, which controls derived menus only.
A page placement may have children and remain a clickable link. Moving a menu
entry never changes page ancestry, breadcrumbs, or routes.

Menu names are unique across derived and explicit menus. A page may appear in
multiple menus, but only once within a given menu. Group IDs must also be unique
within a menu; a group may share an ID with a page because they are distinct
concepts. Missing page references and duplicate placements fail validation even
when their visibility callbacks would hide them. Builders are snapshotted at
registration, just like pages.

`current` identifies an exact page match. `active` includes that match and page
ancestors of the active page, even when the active page is absent from navigation.
Explicit menu parents also become active when a visible child entry is active.
Groups are never current. Empty explicit groups are omitted; linked page entries
remain when all of their children are hidden.

### Request-time visibility

`visibleWhen()` applies to explicit page placements and groups:

```php
use WavyKit\Pages\PageContext;

$pages->defineMenu('account',
    Menu::page('account')->visibleWhen(
        fn (PageContext $context): bool => $context->user !== null,
    ),
);
```

`PageContext::$user` is Laravel's current request user, implementing
`Illuminate\Contracts\Auth\Authenticatable`, or null for a guest. For policy-based
visibility, call your application's Gate/policy checks from the predicate. A
predicate must return bool; exceptions and invalid results identify the menu
and entry. No user data or visibility result is cached on the shared registry.

A hidden entry's label, destination URL, and descendants are not resolved.
An empty group's label is not resolved. This allows visibility to guard entries
whose labels or URLs require unavailable resource parameters. Callbacks must use
the supplied request context rather than capturing a user at registration time.
Hiding an entry never enforces HTTP authorization: retain application middleware
and policy checks on routes/controllers.

### Rendering navigation

For example, create `resources/views/partials/page-menu.blade.php`:

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
                @include('partials.page-menu', ['items' => $item->children])
            @endif
        </li>
    @endforeach
</ul>
```

Render it inside an appropriately labelled navigation element with
`@include('partials.page-menu', ['items' => $pages->navigation('primary')])`.

An unregistered active route returns a null page and empty breadcrumbs; global
menus still render with no active state. Unknown menus and missing route names
raise errors. Ancestor URL generation forwards matching destination path parameters
only. Missing required parameters raise an exception identifying the page and route,
with Laravel's URL-generation exception preserved as the cause.

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
    ->navigationVisible(false)
    ->children(
        Page::make('projects.edit')->route('projects.edit')->label('Edit'),
    );
```

Register this builder with the registry as above. On the edit route, the detail
ancestor uses the bound project to resolve its label and URL. Both resource pages
are omitted from the derived menu because the detail page hides its subtree;
Projects remains active through page ancestry.

`$pages->context()` exposes the active page and route parameters. Ancestor callbacks
see the same active page as the current breadcrumb. `parameter('name')` returns
the bound value or throws when the name is absent. Labels must return strings.
Page definitions retain callbacks; breadcrumbs and navigation contain resolved data.

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
registration. Hidden derived-menu branches do not execute label or parameter
callbacks. Resolved values are not cached on the shared registry. Contexts are
request snapshots and should not be stored on application singletons.

## Development

```sh
composer install
composer check
```

`composer check` validates metadata/lockfile, checks Pint formatting, runs PHPStan
at maximum level on source, and executes Pest unit and Testbench integration tests.
`composer format` applies formatting. `composer.lock` is local and ignored; fresh
checkouts resolve dependencies within the declared constraints. No CI workflow is installed.

## Verification limits

Tests cover static and bound-resource output, parameter mapping, menu nesting and
overrides, validation errors, and request isolation. The suite is also checked against a lowest-installable dependency
resolution. That older dependency set emits a Symfony Translation deprecation
under PHP 8.5; diagnostics remain visible and are not suppressed.

PHP 8.3 is the declared minimum and dependency-resolution platform;
runtime verification currently uses PHP 8.5. PHP 8.3/8.4 runtime runs and a real
consumer application trial remain before public release. No production readiness
or framework-neutral compatibility is implied.

This is a standalone Composer package with its own Git repository, dependencies,
and checks. No workspace CLI is needed to develop or consume it.
