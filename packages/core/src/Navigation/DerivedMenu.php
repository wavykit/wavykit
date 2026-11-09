<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use Closure;
use WavyKit\Exceptions\InvalidDefinition;

/** A registered hierarchy-derived menu; chain exclusions before the registry is resolved. */
final class DerivedMenu
{
    /** @var list<string> */
    private array $except = [];

    /**
     * @internal Created by MenuRegistry::derive().
     *
     * @param  Closure(): void  $assertMutable
     */
    public function __construct(private readonly Closure $assertMutable) {}

    /**
     * Omit these pages, and their subtrees, from this menu only.
     *
     * Breadcrumbs, other menus, and ancestor active state are unaffected; an
     * excluded page can still be placed in an explicit menu. Every ID must be a
     * registered page. This is presentation, never HTTP authorization.
     *
     * @throws InvalidDefinition If the menu registry has been resolved.
     */
    public function except(string ...$pageIds): self
    {
        ($this->assertMutable)();
        array_push($this->except, ...array_values($pageIds));

        return $this;
    }

    /**
     * @internal Read by MenuRegistry when it finalizes.
     *
     * @return list<string>
     */
    public function exclusions(): array
    {
        return $this->except;
    }
}
