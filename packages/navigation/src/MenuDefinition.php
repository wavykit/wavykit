<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

use Closure;
use WavyKit\Pages\PageContext;

/** Immutable placement snapshot; group IDs identify presentation, page IDs reference definitions. */
final readonly class MenuDefinition
{
    /**
     * @param  string|Closure(PageContext): mixed|null  $label
     * @param  null|Closure(PageContext): mixed  $visibility
     * @param  list<MenuDefinition>  $children
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
