<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use LogicException;

final class PageRegistry
{
    /** @var array<string, PageDefinition> */
    private array $definitions = [];

    /** @var array<string, list<string>> */
    private array $menus = [];

    /** @var array<string, list<MenuDefinition>> */
    private array $explicitMenus = [];

    private bool $finalized = false;

    public function register(Page ...$pages): void
    {
        $this->assertMutable();
        $pending = [];
        foreach ($pages as $page) {
            $this->collect($page, null, $pending);
        }

        $this->definitions += $pending;
    }

    public function deriveMenu(string $name, string ...$roots): void
    {
        $this->assertMutable();
        $this->assertMenuName($name);
        $this->menus[$name] = array_values($roots);
    }

    public function defineMenu(string $name, Menu ...$entries): void
    {
        $this->assertMutable();
        $this->assertMenuName($name);
        $this->explicitMenus[$name] = array_map(fn (Menu $entry): MenuDefinition => $this->snapshotMenu($entry, []), array_values($entries));
    }

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

    /** @return list<PageDefinition> */
    public function all(): array
    {
        $this->finalize();

        return array_values($this->definitions);
    }

    public function page(string $id): PageDefinition
    {
        $this->finalize();

        return $this->lookup($id);
    }

    public function forRoute(?string $name): ?PageDefinition
    {
        foreach ($this->all() as $page) {
            if ($name !== null && $page->routeName === $name) {
                return $page;
            }
        }

        return null;
    }

    /** @return list<PageDefinition> Root first, including the requested page. */
    public function trail(string $id): array
    {
        return array_reverse($this->walk($this->page($id)));
    }

    /** @return list<PageDefinition> */
    public function children(?string $parentId): array
    {
        $pages = array_values(array_filter($this->all(), fn (PageDefinition $page): bool => $page->parentId === $parentId));
        usort($pages, fn (PageDefinition $a, PageDefinition $b): int => $a->order <=> $b->order);

        return $pages;
    }

    /** @return list<PageDefinition> */
    public function menuRoots(string $name): array
    {
        $this->finalize();
        if (! array_key_exists($name, $this->menus)) {
            throw new LogicException("Unknown menu '{$name}'.");
        }

        return array_map(fn (string $id): PageDefinition => $this->lookup($id), $this->menus[$name]);
    }

    /** @return list<MenuDefinition>|null Null selects a hierarchy-derived menu. */
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
