<?php

declare(strict_types=1);

namespace WavyKit\Pages;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;

/** A snapshot of the active page and the current request's bound parameters. */
final readonly class PageContext
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public ?PageDefinition $page,
        private array $parameters,
        public ?Authenticatable $user = null,
    ) {}

    public function parameter(string $name): mixed
    {
        if (! array_key_exists($name, $this->parameters)) {
            $pageId = $this->page->id ?? '(unregistered)';
            throw new LogicException("Active page '{$pageId}' is missing route parameter '{$name}'.");
        }

        return $this->parameters[$name];
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
