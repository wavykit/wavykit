<?php

declare(strict_types=1);

namespace WavyKit\Inertia;

use Illuminate\Container\Container;
use Inertia\PropertyContext;
use Inertia\ProvidesInertiaProperty;
use stdClass;
use WavyKit\Current;
use WavyKit\Exceptions\InvalidDefinition;
use WavyKit\Exceptions\ResolutionFailed;
use WavyKit\Navigation\Breadcrumb;

/**
 * Shares the current page ID, breadcrumbs, and selected menus as one Inertia prop.
 *
 * Place it under a key of your choice in HandleInertiaRequests::share(). It
 * resolves when the response renders, after route-model binding, and not at
 * all when a partial reload excludes its key. The shape is
 * `{ pageId, breadcrumbs, menus }`, matching the package's TypeScript types.
 */
final class WavyKitProps implements ProvidesInertiaProperty
{
    /** @var list<string> */
    private array $menus = [];

    private function __construct() {}

    /** Start a prop that shares the page ID and breadcrumbs, and no menus. */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Share these registered menus, keyed by name, in the given order.
     *
     * Names are checked against registered menus when the prop resolves.
     *
     * @throws InvalidDefinition If a name is empty or already shared.
     */
    public function menus(string ...$names): self
    {
        foreach ($names as $name) {
            if (trim($name) === '' || in_array($name, $this->menus, true)) {
                throw new InvalidDefinition("Menu '{$name}' must have a non-empty name and be shared once.");
            }
            $this->menus[] = $name;
        }

        return $this;
    }

    /**
     * @internal Called by Inertia when the response renders.
     *
     * @return array{pageId: ?string, breadcrumbs: list<Breadcrumb>, menus: stdClass}
     *
     * @throws InvalidDefinition If definitions or route references are invalid.
     * @throws ResolutionFailed If a menu is unknown, or callbacks or destination URLs cannot be resolved.
     */
    public function toInertiaProperty(PropertyContext $prop): array
    {
        $current = Container::getInstance()->make(Current::class);

        // An object, so an empty set still serializes as `{}` for the TypeScript record.
        $menus = new stdClass;
        foreach ($this->menus as $name) {
            $menus->{$name} = $current->menu($name);
        }

        return [
            'pageId' => $current->pageId(),
            'breadcrumbs' => $current->breadcrumbs(),
            'menus' => $menus,
        ];
    }
}
