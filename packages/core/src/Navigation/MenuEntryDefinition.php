<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use Closure;
use WavyKit\Context;

/** Immutable entry snapshot; group IDs identify presentation, page IDs reference definitions. */
final readonly class MenuEntryDefinition
{
    /**
     * @internal Created by MenuRegistry from a MenuEntry builder.
     *
     * @param  string|Closure(Context): mixed|null  $label
     * @param  null|Closure(Context): mixed  $visibility
     * @param  list<MenuEntryDefinition>  $children
     */
    public function __construct(
        public string $id,
        public bool $isGroup,
        public string|Closure|null $label,
        public ?string $icon,
        public ?Closure $visibility,
        public array $children,
    ) {}
}
