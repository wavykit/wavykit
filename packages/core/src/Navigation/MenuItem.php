<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

/**
 * Resolved presentation data for the current request; contains no callbacks.
 *
 * Current identifies an exact page match; active also includes page ancestors
 * and explicit menu parents with active visible children. Groups are never current.
 * Groups have null pageId and URL; route-less page entries also have null URLs.
 * Icons are optional identifiers interpreted by the consuming application.
 */
final readonly class MenuItem
{
    /**
     * @param  list<MenuItem>  $children  Already filtered and resolved descendants.
     * @param  string  $kind  Either page or group for package-generated output.
     */
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
