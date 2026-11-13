<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Throwable;
use WavyKit\Context;
use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Exceptions\ResolutionFailed;
use WavyKit\Support\Label;

/** Resolves registered pages for the current Laravel request; does not create routes or enforce access. */
final readonly class Pages
{
    /** @internal Resolved from the container, scoped to the request or job. */
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
     * @throws InvalidDefinition If definitions or route references are invalid.
     */
    public function current(): ?PageDefinition
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
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If route parameters or the current user are invalid.
     */
    public function context(): Context
    {
        $parameters = [];
        foreach ($this->activeRoute()?->parameters() ?? [] as $name => $value) {
            if (! is_string($name)) {
                throw new ResolutionFailed('Active route parameters must be keyed by name.');
            }
            $parameters[$name] = $value;
        }

        $user = $this->router->current() === null ? null : $this->router->getCurrentRequest()->user();
        if ($user !== null && ! $user instanceof Authenticatable) {
            throw new ResolutionFailed('The current request user must implement Authenticatable.');
        }

        return new Context($this->current(), $parameters, $user);
    }

    /**
     * Read the active page's ancestry, root first; empty when no page is active.
     *
     * @return list<PageDefinition>
     *
     * @throws InvalidDefinition If registry finalization fails.
     */
    public function activeTrail(Context $context): array
    {
        return $context->page === null ? [] : $this->registry->trail($context->page->id);
    }

    /**
     * Resolve a page's label, running its callback with the given context.
     *
     * @throws ResolutionFailed If the callback throws or does not return a string.
     */
    public function label(PageDefinition $page, Context $context): string
    {
        return Label::resolve($page->label, $context, "page '{$page->id}'");
    }

    /**
     * Generate a page's URL, or null for a route-less page.
     *
     * Forwards only parameters the destination route declares, from the page's
     * route-parameter mapping when present, otherwise from the active route.
     *
     * @throws InvalidDefinition If the page references an unknown route.
     * @throws ResolutionFailed If the mapping is invalid or URL generation fails.
     */
    public function url(PageDefinition $page, Context $context): ?string
    {
        if ($page->routeName === null) {
            return null;
        }
        $route = $this->router->getRoutes()->getByName($page->routeName)
            ?? throw new InvalidDefinition("Page '{$page->id}' references unknown route '{$page->routeName}'.");
        $activeParameters = $context->parameters();
        if ($page->routeParameters !== null) {
            try {
                $mapped = ($page->routeParameters)($context);
            } catch (Throwable $exception) {
                throw new ResolutionFailed("Could not resolve route parameters for page '{$page->id}' and route '{$page->routeName}': {$exception->getMessage()}", 0, $exception);
            }
            if (! is_array($mapped)) {
                throw new ResolutionFailed("Route-parameter callback for page '{$page->id}' must return an array keyed by route parameter name.");
            }
            $activeParameters = [];
            foreach ($mapped as $name => $value) {
                if (! is_string($name)) {
                    throw new ResolutionFailed("Route-parameter callback for page '{$page->id}' must return an array keyed by route parameter name.");
                }
                $activeParameters[$name] = $value;
            }
        }
        $parameters = [];
        foreach ($route->parameterNames() as $name) {
            if (! is_string($name)) {
                throw new InvalidDefinition("Route '{$page->routeName}' contains an invalid parameter name.");
            }
            if (array_key_exists($name, $activeParameters)) {
                $parameters[$name] = $activeParameters[$name];
            }
        }

        try {
            return $this->urls->route($page->routeName, $parameters);
        } catch (Throwable $exception) {
            throw new ResolutionFailed("Could not generate URL for page '{$page->id}' and route '{$page->routeName}': {$exception->getMessage()}", 0, $exception);
        }
    }

    private function validateRoutes(): void
    {
        foreach ($this->registry->all() as $page) {
            if ($page->routeName !== null && $this->router->getRoutes()->getByName($page->routeName) === null) {
                throw new InvalidDefinition("Page '{$page->id}' references unknown route '{$page->routeName}'.");
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
