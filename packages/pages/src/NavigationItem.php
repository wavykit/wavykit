<?php

declare(strict_types=1);

namespace WavyKit\Pages;

/** Resolved presentation data, safe to keep only for the current request. */
final readonly class NavigationItem
{
    /** @param list<NavigationItem> $children */
    public function __construct(
        public ?string $pageId,
        public string $label,
        public ?string $url,
        public bool $current,
        public bool $active,
        public array $children,
        public ?string $icon = null,
        public string $kind = 'page',
    ) {}
}
