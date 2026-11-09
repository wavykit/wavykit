<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Closure;
use WavyKit\Context;

/** Immutable registration snapshot; callbacks remain unevaluated until output is requested. */
final readonly class PageDefinition
{
    /**
     * @internal Created by PageRegistry from a Page builder.
     *
     * @param  string|Closure(Context): mixed  $label
     * @param  null|Closure(Context): mixed  $routeParameters
     */
    public function __construct(
        public string $id,
        public string|Closure $label,
        public ?string $routeName,
        public ?string $parentId,
        public int $order,
        public ?Closure $routeParameters = null,
        public ?string $icon = null,
    ) {}
}
