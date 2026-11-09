<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use LogicException;

/**
 * Named menu declarations: hierarchy-derived roots and explicit placements.
 *
 * Holds declarations only; validation receives the finalized page definitions.
 *
 * @internal Owned by PageRegistry until menus move to wavykit/navigation.
 */
final class MenuRegistry
{
    /** @var array<string, list<string>> */
    private array $derived = [];

    /** @var array<string, list<MenuDefinition>> */
    private array $defined = [];

    /**
     * Declare a menu following the selected page subtrees, in the supplied order.
     *
     * @throws LogicException If the menu name is empty or already used.
     */
    public function derive(string $name, string ...$roots): void
    {
        $this->assertName($name);
        $this->derived[$name] = array_values($roots);
    }

    /**
     * Snapshot an independently arranged menu; a failed snapshot registers nothing.
     *
     * @throws LogicException If the menu name is empty or already used, or an entry is invalid.
     */
    public function define(string $name, Menu ...$entries): void
    {
        $this->assertName($name);
        $this->defined[$name] = array_map(fn (Menu $entry): MenuDefinition => $this->snapshot($entry, []), array_values($entries));
    }

    /**
     * Check page references and duplicate placements, including hidden branches.
     *
     * @param  array<string, PageDefinition>  $pages  Finalized definitions keyed by ID, in registration order.
     *
     * @throws LogicException For unknown pages or duplicate page or group placements.
     */
    public function validate(array $pages): void
    {
        foreach ($this->derived as $name => $roots) {
            $seen = [];
            foreach ($roots as $id) {
                $page = $pages[$id] ?? throw new LogicException("Unknown page '{$id}'.");
                $this->validateBranch($name, $page, $pages, $seen);
            }
        }
        foreach ($this->defined as $name => $entries) {
            $seenPages = [];
            $seenGroups = [];
            $this->validateEntries($name, $entries, $pages, $seenPages, $seenGroups);
        }
    }

    /**
     * Read a derived menu's root page IDs in their declared order.
     *
     * @return list<string>
     *
     * @throws LogicException If the name is not a derived menu.
     */
    public function roots(string $name): array
    {
        if (! array_key_exists($name, $this->derived)) {
            throw new LogicException("Unknown menu '{$name}'.");
        }

        return $this->derived[$name];
    }

    /**
     * Read explicit placements without resolving request-dependent values.
     *
     * @return list<MenuDefinition>|null Null selects a hierarchy-derived menu; an empty list is an empty explicit menu.
     *
     * @throws LogicException If the menu is unknown.
     */
    public function entries(string $name): ?array
    {
        if (array_key_exists($name, $this->defined)) {
            return $this->defined[$name];
        }
        if (! array_key_exists($name, $this->derived)) {
            throw new LogicException("Unknown menu '{$name}'.");
        }

        return null;
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
