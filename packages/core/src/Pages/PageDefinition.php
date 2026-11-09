<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;
use WavyKit\Context;

/** Immutable registration snapshot; callbacks remain unevaluated until output is requested. */
final readonly class PageDefinition
{
    /**
     * @param  string|Closure(Context): mixed  $label
     * @param  null|Closure(Context): mixed  $parameters
     */
    public function __construct(
        public string $id,
        public string|Closure $label,
        public ?string $routeName,
        public ?string $parentId,
        public int $order,
        public ?Closure $parameters = null,
        public ?string $icon = null,
    ) {}
}
