<?php

declare(strict_types=1);

namespace WavyKit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Exceptions\ResolutionFailed;
use WavyKit\Navigation\Breadcrumb;
use WavyKit\Navigation\MenuItem;
use WavyKit\Navigation\Navigation;
use WavyKit\Pages\PageDefinition;
use WavyKit\Pages\Pages;

/**
 * Reads the experience of the current request: page, breadcrumbs, menus, and context.
 *
 * Read-only; register pages and menus through PageRegistry and MenuRegistry.
 * Inject this class or use the WavyKit\Facades\Current facade. It holds no
 * request state: every call resolves the request-scoped readers from the
 * container, so it stays correct across requests, queue jobs, and Octane.
 */
final readonly class Current
{
    /** @internal Resolved from the container as a singleton. */
    public function __construct(private Container $container) {}

    /**
     * The active page, or null on unmatched or unregistered routes.
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     */
    public function page(): ?PageDefinition
    {
        return $this->pages()->current();
    }

    /**
     * The active page's ID, or null on unmatched or unregistered routes.
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     */
    public function pageId(): ?string
    {
        return $this->page()?->id;
    }

    /**
     * Whether the active page's ID matches any pattern; `*` is a wildcard.
     *
     * Checks the active page only, not its ancestors. False when no page is active.
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     */
    public function is(string ...$patterns): bool
    {
        $id = $this->pageId();

        return $id !== null && $patterns !== [] && Str::is($patterns, $id);
    }

    /**
     * Breadcrumbs for the active page, root first; empty when no page is active.
     *
     * @return list<Breadcrumb>
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If callbacks or destination URLs cannot be resolved.
     */
    public function breadcrumbs(): array
    {
        return $this->navigation()->breadcrumbs();
    }

    /**
     * A named menu resolved for this request.
     *
     * @return list<MenuItem>
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If the menu is unknown, or callbacks or destination URLs cannot be resolved.
     */
    public function menu(string $name): array
    {
        return $this->navigation()->menu($name);
    }

    /**
     * The request context passed to label, parameter, and visibility callbacks.
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If route parameters or the current user are invalid.
     */
    public function context(): Context
    {
        return $this->pages()->context();
    }

    /**
     * The current user as WavyKit callbacks see it; null for guests.
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If route parameters or the current user are invalid.
     */
    public function user(): ?Authenticatable
    {
        return $this->context()->user;
    }

    private function pages(): Pages
    {
        return $this->container->make(Pages::class);
    }

    private function navigation(): Navigation
    {
        return $this->container->make(Navigation::class);
    }
}
