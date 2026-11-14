<?php

declare(strict_types=1);

namespace WavyKit\Inertia\Tests\Fixtures;

use Illuminate\Contracts\Routing\UrlRoutable;
use LogicException;

/** A bound route value with a name for labels and an ID for URLs. */
final readonly class Project implements UrlRoutable
{
    public function __construct(public string $id, public string $name) {}

    public function getRouteKey(): string
    {
        return $this->id;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null): never
    {
        throw new LogicException('Bound through Route::bind().');
    }

    public function resolveChildRouteBinding($childType, $value, $field): never
    {
        throw new LogicException('Bound through Route::bind().');
    }
}
