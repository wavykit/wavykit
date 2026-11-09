<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;

/** A declaration builder; registration takes a snapshot of its values. */
final class Page
{
    /** @var string|Closure(PageContext): mixed */
    public string|Closure $label;

    /** @var null|Closure(PageContext): mixed */
    public ?Closure $routeParameters = null;

    public ?string $routeName = null;

    public ?string $parentId = null;

    public int $sortOrder = 0;

    public bool $inNavigation = true;

    public ?string $navigationIcon = null;

    /** @var list<Page> */
    public array $childPages = [];

    private function __construct(public readonly string $id)
    {
        $this->label = $id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    /** @param string|callable(PageContext): mixed $label */
    public function label(string|callable $label): self
    {
        $this->label = is_string($label) ? $label : Closure::fromCallable($label);

        return $this;
    }

    public function route(string $name): self
    {
        $this->routeName = $name;

        return $this;
    }

    /** @param callable(PageContext): mixed $parameters */
    public function parameters(callable $parameters): self
    {
        $this->routeParameters = Closure::fromCallable($parameters);

        return $this;
    }

    public function parent(string $id): self
    {
        $this->parentId = $id;

        return $this;
    }

    public function order(int $order): self
    {
        $this->sortOrder = $order;

        return $this;
    }

    public function navigationVisible(bool $visible): self
    {
        $this->inNavigation = $visible;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function children(self ...$pages): self
    {
        $this->childPages = array_values($pages);

        return $this;
    }
}
