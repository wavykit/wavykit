// TypeScript types for the prop shared by WavyKit\Inertia\WavyKitProps.
// Hand-written to match wavykit/core's JSON output; versioned with this package.

/** A breadcrumb, root first; the current page keeps its URL. */
export type Breadcrumb = {
    pageId: string;
    label: string;
    /** Null for route-less pages. */
    url: string | null;
    current: boolean;
    icon: string | null;
};

/** `page` links to a page; `group` is a presentation group with no page or URL. */
export type MenuItemKind = 'page' | 'group';

/** A resolved menu item. */
export type MenuItem = {
    /** Null for groups. */
    pageId: string | null;
    label: string;
    /** Null for groups and route-less pages. */
    url: string | null;
    /** The exact current page. */
    current: boolean;
    /** The current page, one of its ancestors, or a parent of an active entry. */
    active: boolean;
    children: MenuItem[];
    icon: string | null;
    kind: MenuItemKind;
};

/**
 * The shared prop. Pass the names given to `menus()` to type the menus,
 * for example `WavyKit<'primary' | 'footer'>`.
 */
export type WavyKit<MenuName extends string = string> = {
    /** Null on unregistered routes. */
    pageId: string | null;
    /** Empty on unregistered routes. */
    breadcrumbs: Breadcrumb[];
    menus: Record<MenuName, MenuItem[]>;
};
