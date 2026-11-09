<?php

declare(strict_types=1);

namespace WavyKit\Pages\Laravel;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use LogicException;
use Throwable;
use WavyKit\Pages\MenuDefinition;
use WavyKit\Pages\NavigationItem;
use WavyKit\Pages\PageContext;
use WavyKit\Pages\PageDefinition;
use WavyKit\Pages\PageRegistry;

/** Laravel request adapter for registered metadata; does not create routes or enforce access. */
final readonly class Pages
{
    public function __construct(
        private PageRegistry $registry,
        private Router $router,
        private UrlGenerator $urls,
    ) {}

    /**
     * Resolve the current request's exact named-route association.
     *
     * Returns null for unmatched or unregistered routes; callbacks remain unevaluated.
     * Resolve after routes have loaded. This validates all registered route references.
     *
     * @throws LogicException If definitions or route references are invalid.
     */
    public function page(): ?PageDefinition
    {
        $this->validateRoutes();

        return $this->registry->forRoute($this->activeRoute()?->getName());
    }

    /**
     * Snapshot the active page, bound route parameters, and nullable current user.
     *
     * Resolve after binding middleware when callbacks require models. Do not retain
     * the context across requests or on application singletons.
     *
     * @throws LogicException If definitions, route references, or the current user are invalid.
     */
    public function context(): PageContext
    {
        $parameters = [];
        foreach ($this->activeRoute()?->parameters() ?? [] as $name => $value) {
            if (! is_string($name)) {
                throw new LogicException('Active route parameters must be keyed by name.');
            }
            $parameters[$name] = $value;
        }

        $user = $this->router->current() === null ? null : $this->router->getCurrentRequest()->user();
        if ($user !== null && ! $user instanceof Authenticatable) {
            throw new LogicException('The current request user must implement Authenticatable.');
        }

        return new PageContext($this->page(), $parameters, $user);
    }

    /**
     * Resolve root-first ancestry, including the current page and route-less parents.
     *
     * Returns an empty list for unregistered routes. Current and route-less entries
     * have null URLs. All labels use the same active-request context.
     *
     * @return list<array{pageId: string, label: string, url: ?string, current: bool}>
     *
     * @throws LogicException If definitions, callbacks, or destination URLs cannot be resolved.
     */
    public function breadcrumbs(): array
    {
        $context = $this->context();
        $current = $context->page;
        if ($current === null) {
            return [];
        }

        return array_map(fn (PageDefinition $page): array => [
            'pageId' => $page->id,
            'label' => $this->label($page, $context),
            'url' => $page->id === $current->id ? null : $this->url($page, $context),
            'current' => $page->id === $current->id,
        ], $this->registry->trail($current->id));
    }

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
    public function navigation(string $name): array
    {
        $context = $this->context();
        $current = $context->page;
        $activeIds = $current === null ? [] : array_map(fn (PageDefinition $page): string => $page->id, $this->registry->trail($current->id));

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
                $this->label($page, $context),
                $this->url($page, $context),
                $page->id === $context->page?->id,
                in_array($page->id, $activeIds, true),
                $this->items($this->registry->children($page->id), $context, $activeIds),
                $page->icon,
            );
        }

        return $items;
    }

    private function label(PageDefinition $page, PageContext $context): string
    {
        return $this->resolveLabel($page->label, $context, "page '{$page->id}'");
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
                : ($page === null ? $entry->id : $this->label($page, $context));
            $activeChild = false;
            foreach ($children as $child) {
                $activeChild = $activeChild || $child->active;
            }

            $items[] = new NavigationItem(
                $page?->id,
                $label,
                $page === null ? null : $this->url($page, $context),
                $page !== null && $page->id === $context->page?->id,
                $activeChild || ($page !== null && in_array($page->id, $activeIds, true)),
                $children,
                $entry->icon ?? $page?->icon,
                $entry->isGroup ? 'group' : 'page',
            );
        }

        return $items;
    }

    private function url(PageDefinition $page, PageContext $context): ?string
    {
        if ($page->routeName === null) {
            return null;
        }
        $route = $this->router->getRoutes()->getByName($page->routeName)
            ?? throw new LogicException("Page '{$page->id}' references unknown route '{$page->routeName}'.");
        $activeParameters = $context->parameters();
        if ($page->parameters !== null) {
            try {
                $mapped = ($page->parameters)($context);
            } catch (Throwable $exception) {
                throw new LogicException("Could not resolve parameters for page '{$page->id}' and route '{$page->routeName}': {$exception->getMessage()}", 0, $exception);
            }
            if (! is_array($mapped)) {
                throw new LogicException("Parameter callback for page '{$page->id}' must return an array keyed by route parameter name.");
            }
            $activeParameters = [];
            foreach ($mapped as $name => $value) {
                if (! is_string($name)) {
                    throw new LogicException("Parameter callback for page '{$page->id}' must return an array keyed by route parameter name.");
                }
                $activeParameters[$name] = $value;
            }
        }
        $parameters = [];
        foreach ($route->parameterNames() as $name) {
            if (! is_string($name)) {
                throw new LogicException("Route '{$page->routeName}' contains an invalid parameter name.");
            }
            if (array_key_exists($name, $activeParameters)) {
                $parameters[$name] = $activeParameters[$name];
            }
        }

        try {
            return $this->urls->route($page->routeName, $parameters);
        } catch (Throwable $exception) {
            throw new LogicException("Could not generate URL for page '{$page->id}' and route '{$page->routeName}': {$exception->getMessage()}", 0, $exception);
        }
    }

    private function validateRoutes(): void
    {
        foreach ($this->registry->all() as $page) {
            if ($page->routeName !== null && $this->router->getRoutes()->getByName($page->routeName) === null) {
                throw new LogicException("Page '{$page->id}' references unknown route '{$page->routeName}'.");
            }
        }
    }

    private function activeRoute(): ?Route
    {
        if ($this->router->current() === null) {
            return null;
        }

        // Older router versions retain their previous route after an unmatched request.
        return $this->router->getCurrentRequest()->route();
    }
}
