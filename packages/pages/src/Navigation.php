<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;
use LogicException;
use Throwable;

/**
 * Resolves named menus into presentation data for the current request.
 *
 * Page labels, URLs, context, and active ancestry come from the Pages resolver.
 *
 * @internal Backs Pages::navigation() until menus move to wavykit/navigation.
 */
final readonly class Navigation
{
    public function __construct(
        private Pages $pages,
        private PageRegistry $registry,
    ) {}

    /**
     * Resolve a named menu into presentation data for this request.
     *
     * Current marks the exact page; active also marks page ancestors and explicit
     * menu parents with active visible children. Unregistered routes have no active
     * items. Callbacks may run repeatedly; output is not cached on the registry.
     *
     * @return list<NavigationItem>
     *
     * @throws LogicException If the menu, definitions, callbacks, or destination URLs are invalid.
     */
    public function menu(string $name): array
    {
        $context = $this->pages->context();
        $activeIds = array_map(fn (PageDefinition $page): string => $page->id, $this->pages->activeTrail($context));

        $entries = $this->registry->menuEntries($name);

        return $entries === null
            ? $this->items($this->registry->menuRoots($name), $context, $activeIds)
            : $this->explicitItems($name, $entries, $context, $activeIds);
    }

    /**
     * @param  list<PageDefinition>  $pages
     * @param  list<string>  $activeIds
     * @return list<NavigationItem>
     */
    private function items(array $pages, PageContext $context, array $activeIds): array
    {
        $items = [];
        foreach ($pages as $page) {
            if (! $page->navigationVisible) {
                continue;
            }
            $items[] = new NavigationItem(
                $page->id,
                $this->pages->label($page, $context),
                $this->pages->url($page, $context),
                $page->id === $context->page?->id,
                in_array($page->id, $activeIds, true),
                $this->items($this->registry->children($page->id), $context, $activeIds),
                $page->icon,
            );
        }

        return $items;
    }

    /**
     * @param  list<MenuDefinition>  $entries
     * @param  list<string>  $activeIds
     * @return list<NavigationItem>
     */
    private function explicitItems(string $name, array $entries, PageContext $context, array $activeIds): array
    {
        $items = [];
        foreach ($entries as $entry) {
            $owner = "menu '{$name}' entry '{$entry->id}'";
            // Guard before resolving resource-dependent labels, URLs, or descendants.
            if ($entry->visibility !== null) {
                try {
                    $visible = ($entry->visibility)($context);
                } catch (Throwable $exception) {
                    throw new LogicException("Could not resolve visibility for {$owner}: {$exception->getMessage()}", 0, $exception);
                }
                if (! is_bool($visible)) {
                    throw new LogicException("Visibility callback for {$owner} must return a bool.");
                }
                if (! $visible) {
                    continue;
                }
            }

            $children = $this->explicitItems($name, $entry->children, $context, $activeIds);
            if ($entry->isGroup && $children === []) {
                continue;
            }
            $page = $entry->isGroup ? null : $this->registry->page($entry->id);
            $label = $entry->label !== null
                ? $this->resolveLabel($entry->label, $context, $owner)
                : ($page === null ? $entry->id : $this->pages->label($page, $context));
            $activeChild = false;
            foreach ($children as $child) {
                $activeChild = $activeChild || $child->active;
            }

            $items[] = new NavigationItem(
                $page?->id,
                $label,
                $page === null ? null : $this->pages->url($page, $context),
                $page !== null && $page->id === $context->page?->id,
                $activeChild || ($page !== null && in_array($page->id, $activeIds, true)),
                $children,
                $entry->icon ?? $page?->icon,
                $entry->isGroup ? 'group' : 'page',
            );
        }

        return $items;
    }

    /** @param string|Closure(PageContext): mixed $value */
    private function resolveLabel(string|Closure $value, PageContext $context, string $owner): string
    {
        if (is_string($value)) {
            return $value;
        }
        try {
            $label = $value($context);
        } catch (Throwable $exception) {
            throw new LogicException("Could not resolve label for {$owner}: {$exception->getMessage()}", 0, $exception);
        }
        if (! is_string($label)) {
            throw new LogicException("Label callback for {$owner} must return a string.");
        }

        return $label;
    }
}
