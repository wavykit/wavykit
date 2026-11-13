<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Exceptions\ResolutionFailed;
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

    /** @var array<string, list<MenuEntryDefinition>> */
    private array $defined = [];

    private bool $finalized = false;

    /** @internal Resolved from the container as a singleton. */
    public function __construct(private readonly PageRegistry $pages) {}

    /**
     * Declare a menu following the selected page subtrees.
     *
     * Root IDs retain supplied order; descendants use page sibling order.
     * Overlapping roots are rejected at finalization, even when excluded.
     *
     * @throws InvalidDefinition If finalized, or the menu name is empty or already used.
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
     * @throws InvalidDefinition If finalized, or menu names or entry structures are invalid.
     */
    public function define(string $name, MenuEntry ...$entries): DefinedMenu
    {
        $this->assertMutable();
        $this->assertName($name);
        $this->defined[$name] = array_map(fn (MenuEntry $entry): MenuEntryDefinition => $this->snapshot($entry, []), array_values($entries));

        return new DefinedMenu;
    }

    /**
     * Validate page references against the finalized page registry and freeze menus.
     *
     * @internal All registry reads invoke this automatically; repeated calls are
     * harmless. A failed validation leaves menus open for correction.
     *
     * @throws InvalidDefinition For invalid pages, unknown page references, or duplicate placements.
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
                $page = $pages[$id] ?? throw new InvalidDefinition("Menu '{$name}' references unknown page '{$id}'.");
                $this->validateBranch($name, $page, $pages, $seen);
            }
            $exclusions[$name] = $this->derivedMenus[$name]->exclusions();
            foreach ($exclusions[$name] as $id) {
                if (! isset($pages[$id])) {
                    throw new InvalidDefinition("Menu '{$name}' excludes unknown page '{$id}'.");
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
     * @internal Read by Navigation.
     *
     * @return list<PageDefinition>
     *
     * @throws InvalidDefinition If finalization fails.
     * @throws ResolutionFailed If the name is not a derived menu.
     */
    public function roots(string $name): array
    {
        $this->finalize();
        if (! array_key_exists($name, $this->derived)) {
            throw new ResolutionFailed("Unknown menu '{$name}'.");
        }

        return array_map(fn (string $id): PageDefinition => $this->pages->page($id), $this->derived[$name]);
    }

    /**
     * Read the page IDs a derived menu omits, with their subtrees.
     *
     * @internal Read by Navigation.
     *
     * @return list<string>
     *
     * @throws InvalidDefinition If finalization fails.
     * @throws ResolutionFailed If the name is not a derived menu.
     */
    public function exclusions(string $name): array
    {
        $this->finalize();

        return $this->exclusions[$name] ?? throw new ResolutionFailed("Unknown menu '{$name}'.");
    }

    /**
     * Read explicit entries without resolving request-dependent values.
     *
     * @internal Read by Navigation.
     *
     * @return list<MenuEntryDefinition>|null Null selects a hierarchy-derived menu; an empty list is an empty explicit menu.
     *
     * @throws InvalidDefinition If finalization fails.
     * @throws ResolutionFailed If the menu is unknown.
     */
    public function entries(string $name): ?array
    {
        $this->finalize();
        if (array_key_exists($name, $this->defined)) {
            return $this->defined[$name];
        }
        if (! array_key_exists($name, $this->derived)) {
            throw new ResolutionFailed("Unknown menu '{$name}'.");
        }

        return null;
    }

    private function assertMutable(): void
    {
        if ($this->finalized) {
            throw new InvalidDefinition('Menus must be registered before the menu registry is resolved.');
        }
    }

    private function assertName(string $name): void
    {
        if (trim($name) === '' || array_key_exists($name, $this->derived) || array_key_exists($name, $this->defined)) {
            throw new InvalidDefinition("Menu '{$name}' must have a unique, non-empty name.");
        }
    }

    /** @param list<int> $ancestors */
    private function snapshot(MenuEntry $entry, array $ancestors): MenuEntryDefinition
    {
        if (trim($entry->id) === '') {
            throw new InvalidDefinition('Menu entries must have non-empty IDs.');
        }
        if (in_array(spl_object_id($entry), $ancestors, true)) {
            throw new InvalidDefinition("Menu entry '{$entry->id}' contains a cycle.");
        }
        $ancestors[] = spl_object_id($entry);

        return $entry->toDefinition(
            array_map(fn (MenuEntry $child): MenuEntryDefinition => $this->snapshot($child, $ancestors), $entry->declaredChildren()));
    }

    /**
     * @param  array<string, PageDefinition>  $pages
     * @param  array<string, bool>  $seen
     */
    private function validateBranch(string $name, PageDefinition $page, array $pages, array &$seen): void
    {
        if (isset($seen[$page->id])) {
            throw new InvalidDefinition("Menu '{$name}' includes page '{$page->id}' more than once.");
        }
        $seen[$page->id] = true;
        foreach ($pages as $child) {
            if ($child->parentId === $page->id) {
                $this->validateBranch($name, $child, $pages, $seen);
            }
        }
    }

    /**
     * @param  list<MenuEntryDefinition>  $entries
     * @param  array<string, PageDefinition>  $pages
     * @param  array<string, bool>  $seenPages
     * @param  array<string, bool>  $seenGroups
     */
    private function validateEntries(string $name, array $entries, array $pages, array &$seenPages, array &$seenGroups): void
    {
        foreach ($entries as $entry) {
            if ($entry->isGroup) {
                if (isset($seenGroups[$entry->id])) {
                    throw new InvalidDefinition("Menu '{$name}' includes group '{$entry->id}' more than once.");
                }
                $seenGroups[$entry->id] = true;
            } else {
                if (! isset($pages[$entry->id])) {
                    throw new InvalidDefinition("Menu '{$name}' references unknown page '{$entry->id}'.");
                }
                if (isset($seenPages[$entry->id])) {
                    throw new InvalidDefinition("Menu '{$name}' includes page '{$entry->id}' more than once.");
                }
                $seenPages[$entry->id] = true;
            }
            $this->validateEntries($name, $entry->children, $pages, $seenPages, $seenGroups);
        }
    }
}
