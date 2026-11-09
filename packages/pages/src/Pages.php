<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use LogicException;
use Throwable;

/** Resolves registered pages for the current Laravel request; does not create routes or enforce access. */
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

        return array_map(fn (PageDefinition $page): array => [
            'pageId' => $page->id,
            'label' => $this->label($page, $context),
            'url' => $page->id === $current?->id ? null : $this->url($page, $context),
            'current' => $page->id === $current?->id,
        ], $this->activeTrail($context));
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
        return (new Navigation($this, $this->registry))->menu($name);
    }

    /**
     * Read the active page's ancestry, root first; empty when no page is active.
     *
     * @return list<PageDefinition>
     *
     * @throws LogicException If registry finalization fails.
     */
    public function activeTrail(PageContext $context): array
    {
        return $context->page === null ? [] : $this->registry->trail($context->page->id);
    }

    /**
     * Resolve a page's label, running its callback with the given context.
     *
     * @throws LogicException If the callback throws or does not return a string.
     */
    public function label(PageDefinition $page, PageContext $context): string
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
     * Generate a page's URL, or null for a route-less page.
     *
     * Forwards only parameters the destination route declares, from the page's
     * parameter mapping when present, otherwise from the active route.
     *
     * @throws LogicException If the route is unknown, the mapping is invalid, or URL generation fails.
     */
    public function url(PageDefinition $page, PageContext $context): ?string
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
