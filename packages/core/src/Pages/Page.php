<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;
use WavyKit\Context;

/** A declaration builder; registration takes a snapshot of its values. */
final class Page
{
    /** @var string|Closure(Context): mixed */
    public string|Closure $label;

    /** @var null|Closure(Context): mixed */
    public ?Closure $routeParameters = null;

    public ?string $routeName = null;

    public ?string $parentId = null;

    public int $sortOrder = 0;

    public ?string $navigationIcon = null;

    /** @var list<Page> */
    public array $childPages = [];

    private function __construct(public readonly string $id)
    {
        $this->label = $id;
    }

    /**
     * Start a declaration with a stable, non-empty ID; the initial label equals the ID.
     *
     * IDs do not imply ancestry and must be unique within the registry.
     */
    public static function make(string $id): self
    {
        return new self($id);
    }

    /**
     * Set the label shared by breadcrumbs and any menus that place this page.
     *
     * Callbacks must return strings and may run repeatedly when output is requested.
     * Use the supplied active-request context rather than capturing request data.
     *
     * @param  string|callable(Context): mixed  $label
     */
    public function label(string|callable $label): self
    {
        $this->label = is_string($label) ? $label : Closure::fromCallable($label);

        return $this;
    }

    /**
     * Associate an existing named Laravel route; no route is created or modified.
     *
     * Each route may belong to only one page. Omit this for a route-less ancestor.
     */
    public function route(string $name): self
    {
        $this->routeName = $name;

        return $this;
    }

    /**
     * Replace the destination URL parameter source using active-request context.
     *
     * Return an array keyed by route parameter name. It replaces, rather than merges
     * with, current parameters; only destination path parameters are forwarded.
     * Callbacks run when a URL is needed and must be free of side effects.
     *
     * @param  callable(Context): mixed  $parameters
     */
    public function parameters(callable $parameters): self
    {
        $this->routeParameters = Closure::fromCallable($parameters);

        return $this;
    }

    /**
     * Reference a parent by page ID, including one registered in a later call.
     *
     * A nested declaration must agree with any explicitly supplied parent.
     */
    public function parent(string $id): self
    {
        $this->parentId = $id;

        return $this;
    }

    /**
     * Set ascending sibling order; ties preserve registration order.
     */
    public function order(int $order): self
    {
        $this->sortOrder = $order;

        return $this;
    }

    /**
     * Set an optional presentation identifier; the application renders the icon.
     */
    public function icon(string $icon): self
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    /**
     * Replace child declarations, assigning this page as their parent at registration.
     *
     * The registry snapshots the entire declaration tree when register() is called.
     */
    public function children(self ...$pages): self
    {
        $this->childPages = array_values($pages);

        return $this;
    }
}
