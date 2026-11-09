<?php

declare(strict_types=1);

namespace WavyKit;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use WavyKit\Pages\PageDefinition;

/**
 * Request snapshot passed to every WavyKit label, parameter, and visibility callback.
 *
 * Page is the active page, not necessarily the ancestor or menu entry being
 * resolved. Page may be null on unregistered routes; user is null for guests.
 * Bound objects are references, not deep copies; do not retain across requests.
 */
final readonly class Context
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public ?PageDefinition $page,
        private array $parameters,
        public ?Authenticatable $user = null,
    ) {}

    /**
     * Read a named bound route value; a present null value is returned unchanged.
     *
     * @throws LogicException If the parameter name is absent.
     */
    public function parameter(string $name): mixed
    {
        if (! array_key_exists($name, $this->parameters)) {
            $pageId = $this->page->id ?? '(unregistered)';
            throw new LogicException("Active page '{$pageId}' is missing route parameter '{$name}'.");
        }

        return $this->parameters[$name];
    }

    /**
     * Read all active-route parameters, including bound models after binding middleware.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
