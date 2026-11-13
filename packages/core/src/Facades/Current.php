<?php

declare(strict_types=1);

namespace WavyKit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Reads the experience of the current request; see WavyKit\Current.
 *
 * @method static \WavyKit\Pages\PageDefinition|null page()
 * @method static string|null pageId()
 * @method static bool is(string ...$patterns)
 * @method static list<\WavyKit\Navigation\Breadcrumb> breadcrumbs()
 * @method static list<\WavyKit\Navigation\MenuItem> menu(string $name)
 * @method static \WavyKit\Context context()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null user()
 *
 * @see \WavyKit\Current
 */
final class Current extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \WavyKit\Current::class;
    }
}
