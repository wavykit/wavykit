<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;
use WavyKit\Context;

/** A declaration builder; registration takes a snapshot of its values. */
final class Page
{
    /** @var string|Closure(Context): mixed */
    private string|Closure $label;

    /** @var null|Closure(Context): mixed */
    private ?Closure $routeParameters = null;

    private ?string $routeName = null;

    private ?string $parentId = null;

    private int $order = 0;

    private ?string $icon = null;

    /** @var list<Page> */
    private array $children = [];

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
     * Replace the source of this page's route parameters using active-request context.
     *
     * Return an array keyed by route parameter name. It replaces, rather than merges
     * with, current parameters; only destination path parameters are forwarded.
     * Callbacks run when a URL is needed and must be free of side effects.
     *
     * @param  callable(Context): mixed  $parameters
     */
    public function routeParameters(callable $parameters): self
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
        $this->order = $order;

        return $this;
    }

    /**
     * Set an optional presentation identifier; the application renders the icon.
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Replace child declarations, assigning this page as their parent at registration.
     *
     * The registry snapshots the entire declaration tree when register() is called.
     */
    public function children(self ...$pages): self
    {
        $this->children = array_values($pages);

        return $this;
    }

    /** @internal Read by PageRegistry during registration. */
    public function declaredParent(): ?string
    {
        return $this->parentId;
    }

    /** @internal Read by PageRegistry during registration. */
    public function declaredRoute(): ?string
    {
        return $this->routeName;
    }

    /**
     * @internal Read by PageRegistry during registration.
     *
     * @return list<Page>
     */
    public function declaredChildren(): array
    {
        return $this->children;
    }

    /** @internal Snapshot taken by PageRegistry, with the resolved parent. */
    public function toDefinition(?string $parentId): PageDefinition
    {
        return new PageDefinition($this->id, $this->label, $this->routeName, $parentId, $this->order, $this->routeParameters, $this->icon);
    }
}
