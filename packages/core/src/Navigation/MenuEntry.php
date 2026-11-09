<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use Closure;
use WavyKit\Context;

/** Builds one entry in an explicit menu: a placement of a page, or a group of entries. */
final class MenuEntry
{
    /** @var string|Closure(Context): mixed|null */
    private string|Closure|null $label = null;

    private ?string $icon = null;

    /** @var null|Closure(Context): mixed */
    private ?Closure $visibility = null;

    /** @var list<MenuEntry> */
    private array $children = [];

    private function __construct(public readonly string $id, private readonly bool $isGroup) {}

    /**
     * Place a registered page by ID without changing its breadcrumb parent.
     *
     * A page may appear once per menu and may be reused across different menus.
     */
    public static function page(string $pageId): self
    {
        return new self($pageId, false);
    }

    /**
     * Create a presentation-only group with no page identity or URL.
     *
     * Group IDs must be unique within a menu. Empty groups are omitted from output.
     */
    public static function group(string $id): self
    {
        return new self($id, true);
    }

    /**
     * Override the placement label, or set a group label.
     *
     * Callbacks must return strings. Without an override, pages use their page label
     * and groups use their group ID. Labels are resolved only for visible output.
     *
     * @param  string|callable(Context): mixed  $label
     */
    public function label(string|callable $label): self
    {
        $this->label = is_string($label) ? $label : Closure::fromCallable($label);

        return $this;
    }

    /**
     * Override the page icon or assign a presentation identifier to a group.
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Filter this entry and its descendants using the current request.
     *
     * Return bool; false skips labels, URLs, and descendant callbacks. The context
     * user may be null. Predicates may run repeatedly; avoid side effects and
     * registration-time request captures. This does not enforce route authorization.
     *
     * @param  callable(Context): mixed  $predicate
     */
    public function visibleWhen(callable $predicate): self
    {
        $this->visibility = Closure::fromCallable($predicate);

        return $this;
    }

    /**
     * Replace children in authored order; page descendants are not imported.
     *
     * A page entry remains a link even when it has children. Registration
     * snapshots entries; moving them does not alter the page hierarchy.
     */
    public function children(self ...$entries): self
    {
        $this->children = array_values($entries);

        return $this;
    }

    /**
     * @internal Read by MenuRegistry during registration.
     *
     * @return list<MenuEntry>
     */
    public function declaredChildren(): array
    {
        return $this->children;
    }

    /**
     * @internal Snapshot taken by MenuRegistry, with already snapshotted children.
     *
     * @param  list<MenuEntryDefinition>  $children
     */
    public function toDefinition(array $children): MenuEntryDefinition
    {
        return new MenuEntryDefinition($this->id, $this->isGroup, $this->label, $this->icon, $this->visibility, $children);
    }
}
