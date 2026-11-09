<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use LogicException;
use WavyKit\Pages\PageDefinition;
use WavyKit\Pages\PageRegistry;

/** Named menu declarations shared across requests; contains no resolved user or resource output. */
final class MenuRegistry
{
    /** @var array<string, list<string>> */
    private array $derived = [];

    /** @var array<string, DerivedMenu> */
    private array $derivedMenus = [];

    /** @var array<string, list<string>> */
    private array $exclusions = [];

    /** @var array<string, list<MenuDefinition>> */
    private array $defined = [];

    private bool $finalized = false;

    public function __construct(private readonly PageRegistry $pages) {}

    /**
     * Declare a menu following the selected page subtrees.
     *
     * Root IDs retain supplied order; descendants use page sibling order.
     * Overlapping roots are rejected at finalization, even when excluded.
     *
     * @throws LogicException If finalized, or the menu name is empty or already used.
     */
    public function derive(string $name, string ...$roots): DerivedMenu
    {
        $this->assertMutable();
        $this->assertName($name);
        $this->derived[$name] = array_values($roots);

        return $this->derivedMenus[$name] = new DerivedMenu($this->assertMutable(...));
    }

    /**
     * Snapshot an independently arranged menu referencing registered pages.
     *
     * Only declared children appear; derived-menu exclusions do not apply. Names
     * are shared with derived menus. A failed snapshot registers nothing.
     *
     * @throws LogicException If finalized, or menu names or placement structures are invalid.
     */
    public function define(string $name, Menu ...$entries): void
    {
        $this->assertMutable();
        $this->assertName($name);
        $this->defined[$name] = array_map(fn (Menu $entry): MenuDefinition => $this->snapshot($entry, []), array_values($entries));
    }

    /**
     * Validate page references against the finalized page registry and freeze menus.
     *
     * All registry reads invoke this automatically; repeated calls are harmless.
     * A failed validation leaves menus open for correction.
     *
     * @throws LogicException For invalid pages, unknown page references, or duplicate placements.
     */
    public function finalize(): void
    {
        if ($this->finalized) {
            return;
        }

        $pages = [];
        foreach ($this->pages->all() as $page) {
            $pages[$page->id] = $page;
        }

        $exclusions = [];
        foreach ($this->derived as $name => $roots) {
            $seen = [];
            foreach ($roots as $id) {
                $page = $pages[$id] ?? throw new LogicException("Menu '{$name}' references unknown page '{$id}'.");
                $this->validateBranch($name, $page, $pages, $seen);
            }
            $exclusions[$name] = $this->derivedMenus[$name]->exclusions();
            foreach ($exclusions[$name] as $id) {
                if (! isset($pages[$id])) {
                    throw new LogicException("Menu '{$name}' excludes unknown page '{$id}'.");
                }
            }
        }
        foreach ($this->defined as $name => $entries) {
            $seenPages = [];
            $seenGroups = [];
            $this->validateEntries($name, $entries, $pages, $seenPages, $seenGroups);
        }

        $this->exclusions = $exclusions;
        $this->finalized = true;
    }

    /**
     * Read a derived menu's roots in their declared order.
     *
     * @return list<PageDefinition>
     *
     * @throws LogicException If the name is not a derived menu or finalization fails.
     */
    public function roots(string $name): array
    {
        $this->finalize();
        if (! array_key_exists($name, $this->derived)) {
            throw new LogicException("Unknown menu '{$name}'.");
        }

        return array_map(fn (string $id): PageDefinition => $this->pages->page($id), $this->derived[$name]);
    }

    /**
     * Read the page IDs a derived menu omits, with their subtrees.
     *
     * @return list<string>
     *
     * @throws LogicException If the name is not a derived menu or finalization fails.
     */
    public function exclusions(string $name): array
    {
        $this->finalize();

        return $this->exclusions[$name] ?? throw new LogicException("Unknown menu '{$name}'.");
    }

    /**
     * Read explicit placements without resolving request-dependent values.
     *
     * @return list<MenuDefinition>|null Null selects a hierarchy-derived menu; an empty list is an empty explicit menu.
     *
     * @throws LogicException If the menu is unknown or finalization fails.
     */
    public function entries(string $name): ?array
    {
        $this->finalize();
        if (array_key_exists($name, $this->defined)) {
            return $this->defined[$name];
        }
        if (! array_key_exists($name, $this->derived)) {
            throw new LogicException("Unknown menu '{$name}'.");
        }

        return null;
    }

    private function assertMutable(): void
    {
        if ($this->finalized) {
            throw new LogicException('Menus must be registered before the menu registry is resolved.');
        }
    }

    private function assertName(string $name): void
    {
        if (trim($name) === '' || array_key_exists($name, $this->derived) || array_key_exists($name, $this->defined)) {
            throw new LogicException("Menu '{$name}' must have a unique, non-empty name.");
        }
    }

    /** @param list<int> $ancestors */
    private function snapshot(Menu $entry, array $ancestors): MenuDefinition
    {
        if (trim($entry->id) === '') {
            throw new LogicException('Menu entries must have non-empty IDs.');
        }
        if (in_array(spl_object_id($entry), $ancestors, true)) {
            throw new LogicException("Menu entry '{$entry->id}' contains a cycle.");
        }
        $ancestors[] = spl_object_id($entry);

        return new MenuDefinition($entry->id, $entry->isGroup, $entry->label, $entry->icon, $entry->visibility,
            array_map(fn (Menu $child): MenuDefinition => $this->snapshot($child, $ancestors), $entry->entries));
    }

    /**
     * @param  array<string, PageDefinition>  $pages
     * @param  array<string, bool>  $seen
     */
    private function validateBranch(string $name, PageDefinition $page, array $pages, array &$seen): void
    {
        if (isset($seen[$page->id])) {
            throw new LogicException("Menu '{$name}' includes page '{$page->id}' more than once.");
        }
        $seen[$page->id] = true;
        foreach ($pages as $child) {
            if ($child->parentId === $page->id) {
                $this->validateBranch($name, $child, $pages, $seen);
            }
        }
    }

    /**
     * @param  list<MenuDefinition>  $entries
     * @param  array<string, PageDefinition>  $pages
     * @param  array<string, bool>  $seenPages
     * @param  array<string, bool>  $seenGroups
     */
    private function validateEntries(string $name, array $entries, array $pages, array &$seenPages, array &$seenGroups): void
    {
        foreach ($entries as $entry) {
            if ($entry->isGroup) {
                if (isset($seenGroups[$entry->id])) {
                    throw new LogicException("Menu '{$name}' includes group '{$entry->id}' more than once.");
                }
                $seenGroups[$entry->id] = true;
            } else {
                if (! isset($pages[$entry->id])) {
                    throw new LogicException("Menu '{$name}' references unknown page '{$entry->id}'.");
                }
                if (isset($seenPages[$entry->id])) {
                    throw new LogicException("Menu '{$name}' includes page '{$entry->id}' more than once.");
                }
                $seenPages[$entry->id] = true;
            }
            $this->validateEntries($name, $entry->children, $pages, $seenPages, $seenGroups);
        }
    }
}
