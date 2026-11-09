<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use LogicException;

/** Declaration store shared across requests; contains no resolved user or resource output. */
final class PageRegistry
{
    /** @var array<string, PageDefinition> */
    private array $definitions = [];

    /** @var array<string, list<string>> */
    private array $menus = [];

    /** @var array<string, list<MenuDefinition>> */
    private array $explicitMenus = [];

    private bool $finalized = false;

    /**
     * Snapshot declarations without resolving labels, URLs, or visibility.
     *
     * Multiple calls may contribute pages, including parents referenced by earlier
     * calls. Complete registration before any read finalizes the registry.
     *
     * @throws LogicException If finalized, or IDs, route names, or nested parents are invalid.
     */
    public function register(Page ...$pages): void
    {
        $this->assertMutable();
        $pending = [];
        foreach ($pages as $page) {
            $this->collect($page, null, $pending);
        }

        $this->definitions += $pending;
    }

    /**
     * Declare a named menu following the selected page subtrees.
     *
     * Root IDs retain supplied order; descendants use sibling order. Overlapping
     * roots are rejected at finalization, even if hidden from navigation.
     *
     * @throws LogicException If finalized, or the menu name is empty or already used.
     */
    public function deriveMenu(string $name, string ...$roots): void
    {
        $this->assertMutable();
        $this->assertMenuName($name);
        $this->menus[$name] = array_values($roots);
    }

    /**
     * Snapshot an independently arranged menu referencing registered pages.
     *
     * Only declared children appear; page visibility defaults do not apply. Names
     * are shared with derived menus. Page and group duplicates are checked on read.
     *
     * @throws LogicException If finalized, or menu names or placement structures are invalid.
     */
    public function defineMenu(string $name, Menu ...$entries): void
    {
        $this->assertMutable();
        $this->assertMenuName($name);
        $this->explicitMenus[$name] = array_map(fn (Menu $entry): MenuDefinition => $this->snapshotMenu($entry, []), array_values($entries));
    }

    /**
     * Validate references and freeze registrations; repeated calls are harmless.
     *
     * All public registry reads invoke this automatically. Laravel route existence
     * is checked separately when resolving output through Laravel\Pages.
     *
     * @throws LogicException For missing or cyclic parents, duplicate route associations, or invalid menus.
     */
    public function finalize(): void
    {
        if ($this->finalized) {
            return;
        }

        $routes = [];
        foreach ($this->definitions as $page) {
            if ($page->routeName !== null) {
                if (isset($routes[$page->routeName])) {
                    throw new LogicException("Route '{$page->routeName}' belongs to multiple pages.");
                }
                $routes[$page->routeName] = true;
            }
            $this->walk($page);
        }

        foreach ($this->menus as $name => $roots) {
            $seen = [];
            foreach ($roots as $id) {
                $this->validateMenuBranch($name, $this->lookup($id), $seen);
            }
        }
        foreach ($this->explicitMenus as $name => $entries) {
            $seenPages = [];
            $seenGroups = [];
            $this->validateEntries($name, $entries, $seenPages, $seenGroups);
        }
        $this->finalized = true;
    }

    /**
     * Read all immutable definitions in registration order, retaining callbacks.
     *
     * @return list<PageDefinition>
     *
     * @throws LogicException If registry finalization fails.
     */
    public function all(): array
    {
        $this->finalize();

        return array_values($this->definitions);
    }

    /**
     * Find a definition by stable page ID without evaluating its callbacks.
     *
     * @throws LogicException If the ID is unknown or registry finalization fails.
     */
    public function page(string $id): PageDefinition
    {
        $this->finalize();

        return $this->lookup($id);
    }

    /**
     * Find an exact named-route association; null or unknown names return null.
     *
     * @throws LogicException If registry finalization fails.
     */
    public function forRoute(?string $name): ?PageDefinition
    {
        foreach ($this->all() as $page) {
            if ($name !== null && $page->routeName === $name) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Read the complete page ancestry, including route-less or navigation-hidden pages.
     *
     * @return list<PageDefinition> Root first, including the requested page.
     *
     * @throws LogicException If the ID is unknown or registry finalization fails.
     */
    public function trail(string $id): array
    {
        return array_reverse($this->walk($this->page($id)));
    }

    /**
     * Read immediate children in ascending order; ties retain registration order.
     *
     * Pass null for roots. An unknown parent ID returns an empty list.
     *
     * @return list<PageDefinition>
     *
     * @throws LogicException If registry finalization fails.
     */
    public function children(?string $parentId): array
    {
        $pages = array_values(array_filter($this->all(), fn (PageDefinition $page): bool => $page->parentId === $parentId));
        usort($pages, fn (PageDefinition $a, PageDefinition $b): int => $a->order <=> $b->order);

        return $pages;
    }

    /**
     * Read a derived menu's roots in their declared order.
     *
     * @return list<PageDefinition>
     *
     * @throws LogicException If the name is not a derived menu or finalization fails.
     */
    public function menuRoots(string $name): array
    {
        $this->finalize();
        if (! array_key_exists($name, $this->menus)) {
            throw new LogicException("Unknown menu '{$name}'.");
        }

        return array_map(fn (string $id): PageDefinition => $this->lookup($id), $this->menus[$name]);
    }

    /**
     * Read explicit placements without resolving request-dependent values.
     *
     * @return list<MenuDefinition>|null Null selects a hierarchy-derived menu; an empty list is an empty explicit menu.
     *
     * @throws LogicException If the menu is unknown or registry finalization fails.
     */
    public function menuEntries(string $name): ?array
    {
        $this->finalize();
        if (array_key_exists($name, $this->explicitMenus)) {
            return $this->explicitMenus[$name];
        }
        if (! array_key_exists($name, $this->menus)) {
            throw new LogicException("Unknown menu '{$name}'.");
        }

        return null;
    }

    /** @param array<string, PageDefinition> $pending */
    private function collect(Page $page, ?string $nestedParent, array &$pending): void
    {
        if (trim($page->id) === '' || isset($pending[$page->id]) || isset($this->definitions[$page->id])) {
            throw new LogicException("Page '{$page->id}' must have a unique, non-empty ID.");
        }
        if ($nestedParent !== null && $page->parentId !== null && $nestedParent !== $page->parentId) {
            throw new LogicException("Page '{$page->id}' has conflicting parents '{$nestedParent}' and '{$page->parentId}'.");
        }
        if ($page->routeName !== null && trim($page->routeName) === '') {
            throw new LogicException("Page '{$page->id}' has an empty route name.");
        }
        $pending[$page->id] = new PageDefinition($page->id, $page->label, $page->routeName, $nestedParent ?? $page->parentId, $page->sortOrder, $page->inNavigation, $page->routeParameters, $page->navigationIcon);
        foreach ($page->childPages as $child) {
            $this->collect($child, $page->id, $pending);
        }
    }

    private function lookup(string $id): PageDefinition
    {
        return $this->definitions[$id] ?? throw new LogicException("Unknown page '{$id}'.");
    }

    /** @return list<PageDefinition> Leaf first. */
    private function walk(PageDefinition $page): array
    {
        $trail = [];
        $seen = [];
        while (true) {
            if (isset($seen[$page->id])) {
                throw new LogicException("Page hierarchy contains a cycle at '{$page->id}'.");
            }
            $seen[$page->id] = true;
            $trail[] = $page;
            if ($page->parentId === null) {
                return $trail;
            }
            $page = $this->lookup($page->parentId);
        }
    }

    /** @param array<string, bool> $seen */
    private function validateMenuBranch(string $name, PageDefinition $page, array &$seen): void
    {
        if (isset($seen[$page->id])) {
            throw new LogicException("Menu '{$name}' includes page '{$page->id}' more than once.");
        }
        $seen[$page->id] = true;
        foreach ($this->definitions as $child) {
            if ($child->parentId === $page->id) {
                $this->validateMenuBranch($name, $child, $seen);
            }
        }
    }

    private function assertMutable(): void
    {
        if ($this->finalized) {
            throw new LogicException('Page definitions and menus must be registered before the registry is resolved.');
        }
    }

    private function assertMenuName(string $name): void
    {
        if (trim($name) === '' || array_key_exists($name, $this->menus) || array_key_exists($name, $this->explicitMenus)) {
            throw new LogicException("Menu '{$name}' must have a unique, non-empty name.");
        }
    }

    /** @param list<int> $ancestors */
    private function snapshotMenu(Menu $entry, array $ancestors): MenuDefinition
    {
        if (trim($entry->id) === '') {
            throw new LogicException('Menu entries must have non-empty IDs.');
        }
        if (in_array(spl_object_id($entry), $ancestors, true)) {
            throw new LogicException("Menu entry '{$entry->id}' contains a cycle.");
        }
        $ancestors[] = spl_object_id($entry);

        return new MenuDefinition($entry->id, $entry->isGroup, $entry->label, $entry->icon, $entry->visibility,
            array_map(fn (Menu $child): MenuDefinition => $this->snapshotMenu($child, $ancestors), $entry->entries));
    }

    /**
     * @param  list<MenuDefinition>  $entries
     * @param  array<string, bool>  $seenPages
     * @param  array<string, bool>  $seenGroups
     */
    private function validateEntries(string $name, array $entries, array &$seenPages, array &$seenGroups): void
    {
        foreach ($entries as $entry) {
            if ($entry->isGroup) {
                if (isset($seenGroups[$entry->id])) {
                    throw new LogicException("Menu '{$name}' includes group '{$entry->id}' more than once.");
                }
                $seenGroups[$entry->id] = true;
            } else {
                if (! isset($this->definitions[$entry->id])) {
                    throw new LogicException("Menu '{$name}' references unknown page '{$entry->id}'.");
                }
                if (isset($seenPages[$entry->id])) {
                    throw new LogicException("Menu '{$name}' includes page '{$entry->id}' more than once.");
                }
                $seenPages[$entry->id] = true;
            }
            $this->validateEntries($name, $entry->children, $seenPages, $seenGroups);
        }
    }
}
