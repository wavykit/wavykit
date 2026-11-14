# WavyKit Inertia

Share WavyKit's page ID, breadcrumbs, and menus with every Inertia page, with
TypeScript types for the shared data.

[`wavykit/core`](https://github.com/wavykit/wavykit/tree/main/packages/core)
describes your application's pages once; this package hands that description
to your React or Vue frontend as one shared prop, so components stop keeping
their own breadcrumb arrays and navigation lists.

Package: `wavykit/inertia`. Namespace: `WavyKit\Inertia\`. Maintained by
WavyKit. Unreleased; it is planned for `v0.2.0`. While WavyKit is on 0.x,
minor releases may change the public API.

## Requirements

PHP 8.3+, Laravel 13, `inertiajs/inertia-laravel` 3, and `wavykit/core` 0.1.

## Installation

```sh
composer require wavykit/inertia
```

Register pages and menus with `wavykit/core` as its README describes.

## Sharing the prop

Add one entry to `share()` in your `HandleInertiaRequests` middleware, under
any key you like, naming the menus every page needs:

```php
use WavyKit\Inertia\WavyKitProps;

public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'wavykit' => WavyKitProps::make()->menus('primary', 'footer'),
    ];
}
```

Every page then receives:

```json
{
    "pageId": "projects.show",
    "breadcrumbs": [
        { "pageId": "projects", "label": "Projects", "url": "https://example.test/projects", "current": false, "icon": null },
        { "pageId": "projects.show", "label": "Apollo", "url": "https://example.test/projects/42", "current": true, "icon": null }
    ],
    "menus": {
        "primary": [
            { "pageId": "projects", "label": "Projects", "url": "https://example.test/projects", "current": false, "active": true, "children": [], "icon": "folder", "kind": "page" }
        ],
        "footer": []
    }
}
```

- `pageId` is null, and `breadcrumbs` empty, on routes without a registered
  page. Menus still render, with no active items.
- `menus` holds the menus passed to `menus()`, in that order. With no
  `menus()` call it is an empty object. Unknown menu names raise
  `WavyKit\Exceptions\ResolutionFailed` when the prop resolves.
- Labels, URLs, and current and active states follow `wavykit/core`'s rules.

The prop resolves when the response renders, after route-model binding, so
labels can use bound models. A partial reload that leaves out its key does not
resolve it at all. To change when it is sent, wrap it in Inertia's own prop
types, for example `Inertia::defer(fn () => WavyKitProps::make()->menus('primary'))`.

## TypeScript types

The package ships hand-written types in
`vendor/wavykit/inertia/resources/types/wavykit.d.ts`, versioned with the PHP
output. Map them to an import path in `tsconfig.json`:

```json
{
    "compilerOptions": {
        "paths": {
            "@wavykit/inertia": ["./vendor/wavykit/inertia/resources/types/wavykit.d.ts"]
        }
    }
}
```

Then type the shared prop, passing your menu names:

```ts
import type { WavyKit } from '@wavykit/inertia';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            wavykit: WavyKit<'primary' | 'footer'>;
        };
    }
}
```

The file also exports `Breadcrumb`, `MenuItem`, and `MenuItemKind` for your
components.

## Development

Development, checks, and releases run from the WavyKit monorepo; see its
`docs/monorepo.md`. This repository is a read-only split.

See [the changelog](CHANGELOG.md) for changes.
