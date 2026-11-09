<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;

/** A menu placement references a page, or groups other placements. */
final class Menu
{
    /** @var string|Closure(PageContext): mixed|null */
    public string|Closure|null $label = null;

    public ?string $icon = null;

    /** @var null|Closure(PageContext): mixed */
    public ?Closure $visibility = null;

    /** @var list<Menu> */
    public array $entries = [];

    private function __construct(public readonly string $id, public readonly bool $isGroup) {}

    public static function page(string $pageId): self
    {
        return new self($pageId, false);
    }

    public static function group(string $id): self
    {
        return new self($id, true);
    }

    /** @param string|callable(PageContext): mixed $label */
    public function label(string|callable $label): self
    {
        $this->label = is_string($label) ? $label : Closure::fromCallable($label);

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** @param callable(PageContext): mixed $predicate */
    public function visibleWhen(callable $predicate): self
    {
        $this->visibility = Closure::fromCallable($predicate);

        return $this;
    }

    public function children(self ...$entries): self
    {
        $this->entries = array_values($entries);

        return $this;
    }
}
