<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use Throwable;
use WavyKit\Context;
use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Exceptions\ResolutionFailed;
use WavyKit\Pages\PageDefinition;
use WavyKit\Pages\PageRegistry;
use WavyKit\Pages\Pages;
use WavyKit\Support\Label;

/**
 * Resolves breadcrumbs and named menus for the current Laravel request; does not enforce access.
 *
 * Page labels, URLs, context, and active ancestry come from the Pages resolver.
 */
final readonly class Navigation
{
    /** @internal Resolved from the container, scoped to the request or job. */
    public function __construct(
        private Pages $pages,
        private PageRegistry $registry,
        private MenuRegistry $menus,
    ) {}

    /**
     * Resolve root-first ancestry, including the current page and route-less parents.
     *
     * Returns an empty list for unregistered routes. Every breadcrumb keeps its URL,
     * including the current one; route-less pages have none. All labels use the
     * same active-request context.
     *
     * @return list<Breadcrumb>
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If callbacks or destination URLs cannot be resolved.
     */
    public function breadcrumbs(): array
    {
        $context = $this->pages->context();
        $current = $context->page;

        return array_map(fn (PageDefinition $page): Breadcrumb => new Breadcrumb(
            $page->id,
            $this->pages->label($page, $context),
            $this->pages->url($page, $context),
            $page->id === $current?->id,
            $page->icon,
        ), $this->pages->activeTrail($context));
    }

    /**
     * Resolve a named menu into presentation data for this request.
     *
     * Current marks the exact page; active also marks page ancestors and explicit
     * menu parents with active visible children. Unregistered routes have no active
     * items. Callbacks may run repeatedly; output is not cached on the registries.
     *
     * @return list<MenuItem>
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If the menu is unknown, or callbacks or destination URLs cannot be resolved.
     */
    public function menu(string $name): array
    {
        $context = $this->pages->context();
        $activeIds = array_map(fn (PageDefinition $page): string => $page->id, $this->pages->activeTrail($context));

        $entries = $this->menus->entries($name);

        return $entries === null
            ? $this->items($this->menus->roots($name), array_flip($this->menus->exclusions($name)), $context, $activeIds)
            : $this->explicitItems($name, $entries, $context, $activeIds);
    }

    /**
     * @param  list<PageDefinition>  $pages
     * @param  array<string, int>  $excluded  Page IDs as keys.
     * @param  list<string>  $activeIds
     * @return list<MenuItem>
     */
    private function items(array $pages, array $excluded, Context $context, array $activeIds): array
    {
        $items = [];
        foreach ($pages as $page) {
            if (isset($excluded[$page->id])) {
                continue;
            }
            $items[] = new MenuItem(
                $page->id,
                $this->pages->label($page, $context),
                $this->pages->url($page, $context),
                $page->id === $context->page?->id,
                in_array($page->id, $activeIds, true),
                $this->items($this->registry->children($page->id), $excluded, $context, $activeIds),
                $page->icon,
            );
        }

        return $items;
    }

    /**
     * @param  list<MenuEntryDefinition>  $entries
     * @param  list<string>  $activeIds
     * @return list<MenuItem>
     */
    private function explicitItems(string $name, array $entries, Context $context, array $activeIds): array
    {
        $items = [];
        foreach ($entries as $entry) {
            $owner = "menu '{$name}' entry '{$entry->id}'";
            // Guard before resolving resource-dependent labels, URLs, or descendants.
            if ($entry->visibility !== null) {
                try {
                    $visible = ($entry->visibility)($context);
                } catch (Throwable $exception) {
                    throw new ResolutionFailed("Could not resolve visibility for {$owner}: {$exception->getMessage()}", 0, $exception);
                }
                if (! is_bool($visible)) {
                    throw new ResolutionFailed("Visibility callback for {$owner} must return a bool.");
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
                ? Label::resolve($entry->label, $context, $owner)
                : ($page === null ? $entry->id : $this->pages->label($page, $context));
            $activeChild = false;
            foreach ($children as $child) {
                $activeChild = $activeChild || $child->active;
            }

            $items[] = new MenuItem(
                $page?->id,
                $label,
                $page === null ? null : $this->pages->url($page, $context),
                $page !== null && $page->id === $context->page?->id,
                $activeChild || ($page !== null && in_array($page->id, $activeIds, true)),
                $children,
                $entry->icon ?? $page?->icon,
                $entry->isGroup ? MenuItemKind::Group : MenuItemKind::Page,
            );
        }

        return $items;
    }
}
