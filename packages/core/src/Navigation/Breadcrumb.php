<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

/**
 * One resolved breadcrumb for the current request; contains no callbacks.
 *
 * The URL is null only for route-less pages; the current page keeps its URL, and
 * renderers use `current` to decide whether to link. Icons are optional
 * identifiers interpreted by the consuming application.
 */
final readonly class Breadcrumb
{
    /** @internal Created by Navigation::breadcrumbs(). */
    public function __construct(
        public string $pageId,
        public string $label,
        public ?string $url,
        public bool $current,
        public ?string $icon = null,
    ) {}
}
